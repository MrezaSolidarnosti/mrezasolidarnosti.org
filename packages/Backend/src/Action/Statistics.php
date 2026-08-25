<?php

namespace Solidarity\Backend\Action;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Entity\Project;

class Statistics extends Html
{
    public function __construct(
        Logger $logger, Config $config, Engine $template,
        private EntityManagerInterface $em,
        \Laminas\Session\ManagerInterface $session,
    ) {
        parent::__construct($logger, $config, $template);
        $storage = $session->getStorage();
        $this->setGlobalVariable('loggedIn', $storage->offsetGet('loggedIn'));
        $this->setGlobalVariable('loggedInEmail', $storage->offsetGet('loggedInEmail'));
        $this->setGlobalVariable('loggedInRole', $storage->offsetGet('loggedInRole'));
        $this->setGlobalVariable('loggedInEntityType', $storage->offsetGet('loggedInEntityType'));
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        $projects = $this->em->getRepository(Project::class)->findAll();

        // Global stats
        $globalStats = $this->getStats();

        // Per-project stats (with per-period breakdown)
        $projectStats = [];
        foreach ($projects as $project) {
            $periods = $this->em->getRepository(Period::class)->findBy(
                ['project' => $project],
                ['year' => 'DESC', 'month' => 'DESC']
            );
            $periodStats = [];
            foreach ($periods as $period) {
                $periodStats[$period->getId()] = [
                    'period' => $period,
                    'stats' => $this->getTransactionStatsByPeriod($project, $period),
                ];
            }
            $projectStats[$project->id] = [
                'project' => $project,
                'stats' => $this->getStats($project),
                'periods' => $periods,
                'periodStats' => $periodStats,
            ];
        }

        return $this->respond('statistics/view', [
            'globalStats' => $globalStats,
            'projectStats' => $projectStats,
            'projects' => $projects,
            'jsPage' => 'Statistics',
        ]);
    }

    private function getStats(?Project $project = null): array
    {
        // "Realized" is CONFIRMED + PAID — the same pair TransactionRepository::
        // getRealizedStatuses() uses for the front page total, so both sides answer "how much
        // was raised" identically. The historical adjustment belongs on that combined figure
        // and nowhere else: the per-status amounts below stay pure database sums.
        $confirmedAmount = $this->getTransactionSumByStatus(Transaction::STATUS_CONFIRMED, $project);
        $paidAmount = $this->getTransactionSumByStatus(Transaction::STATUS_PAID, $project);
        $confirmedCount = $this->getTransactionCountByStatus(Transaction::STATUS_CONFIRMED, $project);
        $paidCount = $this->getTransactionCountByStatus(Transaction::STATUS_PAID, $project);

        return [
            'donorCount' => $this->getDonorCount($project),
            'monthlyDonorCount' => $this->getMonthlyDonorCount($project),
            'beneficiaryCount' => $this->getBeneficiaryCount($project),
            'delegateCount' => $this->getDelegateCount($project),
            'totalPledged' => $this->getTotalPledged($project),
            'monthlyPledged' => $this->getMonthlyPledged($project),
            'confirmedAmount' => $confirmedAmount,
            'confirmedCount' => $confirmedCount,
            'paidAmount' => $paidAmount,
            'paidCount' => $paidCount,

            // The headline "how much was raised" figure, matching the front page.
            // The count is the real row count and is deliberately NOT adjusted — the
            // adjustment is a sum with no rows behind it, so there is no honest number to add.
            'realizedAmount' => $confirmedAmount + $paidAmount + $this->historicalAdjustment($project),
            'realizedCount' => $confirmedCount + $paidCount,
            'historicalAdjustment' => $this->historicalAdjustment($project),
            'historicalAdjustmentNote' => $this->historicalAdjustmentNote($project),
            'activeAmount' => $this->getTransactionSumByStatus(Transaction::STATUS_NEW, $project),
            'activeCount' => $this->getTransactionCountByStatus(Transaction::STATUS_NEW, $project),
            'cancelledAmount' => $this->getTransactionSumByStatus(Transaction::STATUS_CANCELLED, $project),
            'cancelledCount' => $this->getTransactionCountByStatus(Transaction::STATUS_CANCELLED, $project),
            'expiredAmount' => $this->getTransactionSumByStatus(Transaction::STATUS_EXPIRED, $project),
            'expiredCount' => $this->getTransactionCountByStatus(Transaction::STATUS_EXPIRED, $project),
        ];
    }

    private function getDonorCount(?Project $project = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT d.id)')
            ->from(Donor::class, 'd')
            ->where('d.status != :deleted')
            ->setParameter('deleted', Donor::STATUS_DELETED);

        if ($project) {
            $qb->innerJoin('d.projects', 'p')
                ->andWhere('p.id = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getMonthlyDonorCount(?Project $project = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT pm.donor)')
            ->from(DonorPaymentMethod::class, 'pm')
            ->innerJoin('pm.donor', 'd')
            ->where('pm.monthly = 1')
            ->andWhere('d.status != :deleted')
            ->setParameter('deleted', Donor::STATUS_DELETED);

        if ($project) {
            $qb->andWhere('pm.project = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getBeneficiaryCount(?Project $project = null): int
    {
        // Count every beneficiary regardless of status — deleted ones are no longer active
        // but are kept in the total for transparency.
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT b.id)')
            ->from(Beneficiary::class, 'b');

        if ($project) {
            $qb->innerJoin('b.registeredPeriods', 'rp')
                ->where('rp.project = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getDelegateCount(?Project $project = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT d.id)')
            ->from(Delegate::class, 'd')
            ->where('d.status IN (:statuses)')
            ->setParameter('statuses', [Delegate::STATUS_NEW, Delegate::STATUS_VERIFIED]);

        if ($project) {
            $qb->innerJoin('d.projects', 'p')
                ->andWhere('p.id = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * A pledge is a promise about the future, so a deleted donor has none — their rows are
     * excluded here, matching getDonorCount(). Money they already sent is a different
     * question and keeps counting: see getTransactionSumByStatus(), which deliberately does
     * not filter on donor status.
     */
    private function getTotalPledged(?Project $project = null): int
    {
        // RSD amounts (bank transfer type = 1)
        $qbRsd = $this->pledgeQuery(true);

        if ($project) {
            $qbRsd->andWhere('pm.project = :projectId')
                ->setParameter('projectId', $project->id);
        }
        $rsdTotal = (int) $qbRsd->getQuery()->getSingleScalarResult();

        // EUR amounts (all other types), convert to RSD
        $qbEur = $this->pledgeQuery(false);

        if ($project) {
            $qbEur->andWhere('pm.project = :projectId')
                ->setParameter('projectId', $project->id);
        }
        $eurTotal = (int) $qbEur->getQuery()->getSingleScalarResult();

        return $rsdTotal + Transaction::eurToRsd($eurTotal);
    }

    /**
     * Sum of pledged amounts, in whichever currency the payment type implies, from donors
     * who still exist.
     *
     * @param bool $bankTransfer true for the RSD (bank transfer) half, false for the EUR rest
     */
    private function pledgeQuery(bool $bankTransfer): \Doctrine\ORM\QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(pm.amount), 0)')
            ->from(DonorPaymentMethod::class, 'pm')
            ->innerJoin('pm.donor', 'pd')
            ->where(sprintf('pm.type %s :bankType', $bankTransfer ? '=' : '!='))
            ->andWhere('pd.status != :deletedDonor')
            ->setParameter('bankType', DonorPaymentMethod::TYPE_BANK_TRANSFER)
            ->setParameter('deletedDonor', Donor::STATUS_DELETED);
    }

    /** Same rule as getTotalPledged(): deleted donors have no standing pledge. */
    private function getMonthlyPledged(?Project $project = null): int
    {
        // RSD monthly
        $qbRsd = $this->pledgeQuery(true)->andWhere('pm.monthly = 1');

        if ($project) {
            $qbRsd->andWhere('pm.project = :projectId')
                ->setParameter('projectId', $project->id);
        }
        $rsdTotal = (int) $qbRsd->getQuery()->getSingleScalarResult();

        // EUR monthly
        $qbEur = $this->pledgeQuery(false)->andWhere('pm.monthly = 1');

        if ($project) {
            $qbEur->andWhere('pm.project = :projectId')
                ->setParameter('projectId', $project->id);
        }
        $eurTotal = (int) $qbEur->getQuery()->getSingleScalarResult();

        return $rsdTotal + Transaction::eurToRsd($eurTotal);
    }

    /**
     * Confirmed money that no longer has rows behind it.
     *
     * The legacy app deleted donors who had gone inactive, and deleting a donor cascaded to
     * their transactions. That began when the projects were running a **surplus** — more
     * donors pledging than there were requests to fund — a state the old app was not designed
     * for, so the inactivity cleanup ran against people whose confirmed donations had already
     * been paid and counted. Roughly 6,400 donor deletions took their confirmed transactions
     * with them.
     *
     * Nothing survives to rebuild them from. The cascade logged no row contents:
     * `log_entity_change` has no Transaction delete rows at all, its `changes` column is a
     * field diff that never carries `amount`, and `log_command_change` records only a fixed
     * sentence per run. Both legacy databases were searched; the two recovery commands that
     * exist already scraped everything the SF/msdash dumps had.
     *
     * So the figure is carried as a configured adjustment rather than reconstructed as data.
     * Nothing is written to the transaction table: a synthetic row would be indistinguishable
     * from a real donation, would flow into per-donor and per-period views that must stay
     * evidential, and would corrupt exactly the comparisons the retained legacy databases
     * exist to support.
     *
     * Set `historicalAdjustment` in config, keyed by project code. Absent = no adjustment,
     * which is the right default for any project that never had this problem.
     */
    private function historicalAdjustment(?Project $project = null): int
    {
        $config = $this->getConfig()->offsetExists('historicalAdjustment')
            ? $this->getConfig()->offsetGet('historicalAdjustment')
            : null;

        if (!$config) {
            return 0;
        }

        $adjustments = [];
        foreach ($config as $code => $entry) {
            $adjustments[$code] = (int) ($entry->amount ?? 0);
        }

        // No project selected means "all projects", so every adjustment applies.
        if (!$project) {
            return array_sum($adjustments);
        }

        return $adjustments[$project->code] ?? 0;
    }

    /**
     * The backend explanation for the adjustment. Deliberately not surfaced on the public
     * site: the caveat needs context to read correctly, and a footnote on a donation total
     * raises more questions than it answers for a visitor. Anyone who can see the dashboard
     * can see why the number is what it is.
     */
    private function historicalAdjustmentNote(?Project $project = null): string
    {
        $amount = $this->historicalAdjustment($project);
        if ($amount === 0) {
            return '';
        }

        $config = $this->getConfig()->offsetGet('historicalAdjustment');
        $notes = [];
        foreach ($config as $code => $entry) {
            if ($project && $project->code !== $code) {
                continue;
            }
            if ((int) ($entry->amount ?? 0) === 0) {
                continue;
            }
            $notes[] = sprintf('%s: %s RSD — %s', $code, number_format((int) $entry->amount), $entry->note ?? '');
        }

        return implode(' | ', $notes);
    }

    private function getTransactionSumByStatus(int $status, ?Project $project = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(t.amount), 0)')
            ->from(Transaction::class, 't')
            ->where('t.status = :status')
            ->setParameter('status', $status);

        if ($project) {
            $qb->andWhere('t.project = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getTransactionCountByStatus(int $status, ?Project $project = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->where('t.status = :status')
            ->setParameter('status', $status);

        if ($project) {
            $qb->andWhere('t.project = :projectId')
                ->setParameter('projectId', $project->id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function getTransactionStatsByPeriod(Project $project, Period $period): array
    {
        $statuses = [
            'confirmed' => Transaction::STATUS_CONFIRMED,
            'paid' => Transaction::STATUS_PAID,
            'active' => Transaction::STATUS_NEW,
            'cancelled' => Transaction::STATUS_CANCELLED,
        ];

        $result = [];
        foreach ($statuses as $key => $status) {
            $qbSum = $this->em->createQueryBuilder()
                ->select('COALESCE(SUM(t.amount), 0)')
                ->from(Transaction::class, 't')
                ->where('t.status = :status')
                ->andWhere('t.project = :projectId')
                ->andWhere('t.period = :periodId')
                ->setParameter('status', $status)
                ->setParameter('projectId', $project->id)
                ->setParameter('periodId', $period->getId());

            $qbCount = $this->em->createQueryBuilder()
                ->select('COUNT(t.id)')
                ->from(Transaction::class, 't')
                ->where('t.status = :status')
                ->andWhere('t.project = :projectId')
                ->andWhere('t.period = :periodId')
                ->setParameter('status', $status)
                ->setParameter('projectId', $project->id)
                ->setParameter('periodId', $period->getId());

            $result[$key . 'Amount'] = (int) $qbSum->getQuery()->getSingleScalarResult();
            $result[$key . 'Count'] = (int) $qbCount->getQuery()->getSingleScalarResult();
        }

        // Beneficiary count for this period
        $qbBen = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT rp.beneficiary)')
            ->from(\Solidarity\Beneficiary\Entity\RegisteredPeriods::class, 'rp')
            ->where('rp.project = :projectId')
            ->andWhere('rp.period = :periodId')
            ->setParameter('projectId', $project->id)
            ->setParameter('periodId', $period->getId());
        $result['beneficiaryCount'] = (int) $qbBen->getQuery()->getSingleScalarResult();

        return $result;
    }
}
