<?php

namespace Solidarity\Backend\Action;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Config\Config;
use \League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Donor\Service\Donor;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Entity\Transaction as TransactionEntity;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Service\Project;

/**
 * Allocate every active donor's pledges to beneficiaries.
 *
 *   php public/cli.php createTransactions run     — allocate, persist, mail the donors
 *   php public/cli.php createTransactions dry     — preview the whole round, write nothing
 *
 * The flag is "dry", not the migrations' run|commit, on purpose: deploy/crontab already
 * passes "run" to this job, so making "run" mean preview would turn both production rounds
 * into silent no-ops. This matches ExpireInstructions, which is its cron pair.
 */
class CreateTransaction extends Html
{
    public function __construct(
        Logger $logger, Config $config, Engine $template,
        public readonly TransactionService $transaction,
        public readonly Project $project,
        public readonly Donor $donor,
        private Mailer $mailer,
        private EntityManagerInterface $em
    ) {
        parent::__construct($logger, $config, $template);
    }

    /**
     * Each donor is balanced across the projects they pledged to (round-robin), with each
     * project keeping its own pledge; the heavy lifting lives in
     * TransactionService::createBalancedForDonor().
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request request
     * @param \Psr\Http\Message\ResponseInterface $response response
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        // CliSkeletor passes the argv tail as the "params" attribute.
        $params = (array) $request->getAttribute('params', []);
        $dry = in_array('dry', $params, true);

        if ($this->isHoliday()) {
            $this->getLogger()->log(\Monolog\Level::Info, 'Holiday detected. Not creating transactions.');
            // Said out loud rather than returning in silence: an empty dry-run report reads
            // like "this round allocates nothing", which is a different and much more
            // alarming conclusion than "today is a holiday".
            echo $dry
                ? 'DRY-RUN: holiday — a real run today would allocate nothing.' . PHP_EOL
                : 'Holiday detected. Not creating transactions.' . PHP_EOL;

            return $response;
        }

        $projects = [];
        foreach ($this->project->getEntities([], 2) as $project) {
            $projects[] = $project;
        }

        // Unique active donors across all projects. Each donor is then balanced across the
        // projects they pledged to (round-robin), with every project keeping its own pledge.
        $donors = [];
        foreach ($projects as $project) {
            foreach ($this->donor->getDonorsByProject($project) as $donor) {
                $donors[$donor->id] = $donor;
            }
        }

        // A dry run does the real work and then throws it away, rather than skipping the
        // writes. That is not belt-and-braces: allocateToBeneficiary() re-reads
        // getSumAmountForBeneficiary() and getRemainingPerPersonLimit() from the database for
        // every candidate, and only the donor's own per-project budget is tracked in memory.
        // With nothing persisted, each successive donor would see every beneficiary as
        // completely unfunded, the whole round would pile onto the same first few candidates,
        // and the preview would be confidently wrong. Same rollback pattern MigrateLegacy and
        // the recover* actions use.
        //
        // The transaction is opened for the preview ONLY. Wrapping a real round in one too
        // would look tidier and be strictly worse: the mail goes out per donor inside the
        // loop and cannot be rolled back, so a failure at donor 300 would erase the
        // allocations while leaving 299 donors holding payment instructions for transactions
        // that no longer exist. Left on autocommit, a real round keeps exactly the behaviour
        // it has always had — partial progress persists, and it persists in step with the
        // mail already sent.
        $conn = $this->em->getConnection();
        if ($dry) {
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
        }

        $lastId = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(MAX(t.id), 0)')
            ->from(TransactionEntity::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        try {
            foreach ($donors as $donor) {
                $this->getLogger()->log(\Monolog\Level::Info, sprintf('Processing donor %s at %s', $donor->email, date('Y-m-d H:i:s')));
                $this->transaction->createBalancedForDonor($donor, $projects);
                // The one thing a rollback cannot take back. Nothing is sent while previewing.
                if (!$dry) {
                    $this->mailer->sendDonorInstructionsMail($donor->email, $donor->getDisplayName());
                }
            }

            // Built before the rollback, while the rows and their relations are still readable.
            $this->report($this->allocationsSince($lastId), $dry, count($donors));

            if ($dry) {
                $conn->rollBack();
            }
        } catch (\Throwable $e) {
            // Only the preview has anything to undo. Leaving it open would hold locks until
            // the connection dropped; a real round is on autocommit and keeps whatever it
            // managed to allocate, in step with the mail it already sent.
            if ($dry && $conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->getLogger()->error(sprintf(
                'createTransactions (%s) failed: %s',
                $dry ? 'dry' : 'run',
                $e->getMessage()
            ));

            throw $e;
        }

        return $response;
    }

    /**
     * The transactions this run created, newest id first seen.
     *
     * Reading them back from the database rather than having createBalancedForDonor() report
     * them: it returns a total, the allocation detail lives several layers down in a private
     * method, and the rows are right there inside the open transaction.
     *
     * Scalars, not entities. Fetch-joining t.project/t.period hydrates as objects and dies on
     * "Undefined array key transactions" in ObjectHydrator: both associations declare
     * inversedBy: 'transactions', but neither Project nor Period actually has that collection,
     * so the hydrator's lookup of the inverse side misses. A report only needs values anyway,
     * and this skips building throwaway objects for the whole round.
     *
     * @return array<int, array<string, mixed>>
     */
    private function allocationsSince(int $lastId): array
    {
        return $this->em->createQueryBuilder()
            ->select(
                't.amount AS amount',
                't.paymentType AS paymentType',
                'd.email AS donorEmail',
                'd.id AS donorId',
                'b.name AS beneficiaryName',
                'p.code AS projectCode',
                'pe.month AS periodMonth',
                'pe.year AS periodYear',
                'pe.type AS periodType'
            )
            ->from(TransactionEntity::class, 't')
            ->leftJoin('t.donor', 'd')
            ->leftJoin('t.beneficiary', 'b')
            ->join('t.project', 'p')
            ->join('t.period', 'pe')
            ->where('t.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @param TransactionEntity[] $allocations */
    private function report(array $allocations, bool $dry, int $donorCount): void
    {
        $mode = $dry ? 'DRY-RUN' : 'RUN';
        echo sprintf('=== CREATE TRANSACTIONS %s — %s ===', $mode, date('Y-m-d H:i:s')) . PHP_EOL;
        echo sprintf('Donors processed: %d', $donorCount) . PHP_EOL . PHP_EOL;

        if (!$allocations) {
            echo 'No transactions allocated.' . PHP_EOL;
            echo $this->footer($dry);

            return;
        }

        $byProject = [];
        $byType = [];
        $donorsAllocated = [];
        $total = 0;

        echo sprintf(
            "%-4s %-30s %-28s %-6s %-12s %-16s %10s\n",
            '#', 'Donor', 'Beneficiary', 'Proj', 'Period', 'Type', 'RSD'
        );
        foreach ($allocations as $i => $row) {
            $type = PaymentMethod::getHrType((int) $row['paymentType']);
            $amount = (int) $row['amount'];
            $code = (string) $row['projectCode'];
            // Period::getLabel() prefixes the project code, which already has its own column.
            $period = sprintf('%d-%d-%s', $row['periodMonth'], $row['periodYear'], $row['periodType']);

            echo sprintf(
                "%-4d %-30s %-28s %-6s %-12s %-16s %10s\n",
                $i + 1,
                $this->clip((string) ($row['donorEmail'] ?? '-'), 30),
                $this->clip((string) ($row['beneficiaryName'] ?? '-'), 28),
                $code,
                $period,
                $this->clip($type, 16),
                number_format($amount, 0)
            );

            $byProject[$code] ??= ['amount' => 0, 'count' => 0];
            $byProject[$code]['amount'] += $amount;
            $byProject[$code]['count']++;

            $byType[$type] ??= ['amount' => 0, 'count' => 0];
            $byType[$type]['amount'] += $amount;
            $byType[$type]['count']++;

            if ($row['donorId'] !== null) {
                $donorsAllocated[$row['donorId']] = true;
            }
            $total += $amount;
        }

        echo PHP_EOL . 'Totals by project:' . PHP_EOL;
        foreach ($byProject as $code => $sum) {
            echo sprintf('  %-8s %12s RSD  (%d transaction(s))', $code, number_format($sum['amount'], 0), $sum['count']) . PHP_EOL;
        }

        echo 'Totals by payment type:' . PHP_EOL;
        foreach ($byType as $name => $sum) {
            echo sprintf('  %-18s %12s RSD  (%d transaction(s))', $name, number_format($sum['amount'], 0), $sum['count']) . PHP_EOL;
        }

        echo PHP_EOL;
        // Worth its own line: a donor who allocated nothing is the usual symptom of a round
        // that ran but had no unmet need left to hand them.
        echo sprintf('Donors with no allocation: %d', $donorCount - count($donorsAllocated)) . PHP_EOL;
        echo sprintf('TOTAL: %s RSD across %d transaction(s)', number_format($total, 0), count($allocations)) . PHP_EOL;
        echo $this->footer($dry);
    }

    private function footer(bool $dry): string
    {
        return $dry
            ? '=== DRY-RUN — rolled back. Nothing was written and no mail was sent. ===' . PHP_EOL
            : '=== RUN — committed. Donors have been mailed. ===' . PHP_EOL;
    }

    private function clip(string $value, int $width): string
    {
        return mb_strlen($value) > $width ? mb_substr($value, 0, $width - 1) . '…' : $value;
    }

    public function isHoliday(): bool
    {
        $dates = ['01.01', '02.01', '06.01', '07.01', '15.01', '16.01', '17.01', '20.01', '01.05', '02.05', '06.05', '06.12', '11.11', '25.12', '31.12'];

        return in_array(date('d.m'), $dates);
    }

}
