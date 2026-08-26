<?php

namespace Solidarity\Backend\Action;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Transaction;

/**
 * Restores lost legacy instructions (periods 26 & 27) that were hard-deleted along with
 * their beneficiaries. Reads data/recovered_instructions.csv (from the reconciliation) and
 * creates GDPR-redacted transactions: an existing donor + amount + period, with NO
 * beneficiary and NO account number (those beneficiaries were erased). The result is an
 * anonymous accounting record restoring donor and period totals.
 *
 * Dry-run (reports, rolls back):  php public/cli.php recoverInstructions run
 * Commit:                         php public/cli.php recoverInstructions commit
 *
 * Not idempotent — aborts a commit if recovered rows already exist, to avoid duplicates.
 *
 * One-shot recovery command, deleted after the move to production — untested on purpose
 * and excluded from coverage.
 *
 * @codeCoverageIgnore
 */
class RecoverInstructions extends Html
{
    private const CSV = 'recovered_instructions.csv';
    private const PAYMENT_TYPE_BANK = 1;
    private const COMMENT_PREFIX = 'Recovered legacy instruction';
    private const PERIOD_IDS = [26, 27];

    /** @var array<string, ?Donor> email => donor (or null when absent) */
    private array $donorCache = [];
    /** @var array<int, int>|null legacy period id => new id; null until loaded */
    private ?array $periodMap = null;
    /** @var array<int, array{0: object, 1: string}> [entity, 'Y-m-d H:i:s'] to back-date */
    private array $timestamps = [];
    private array $report = ['counts' => [], 'skipped' => []];

    public function __construct(
        Logger $logger, Config $config, Engine $template, private EntityManagerInterface $em,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response,
    ) {
        $params = (array) $request->getAttribute('params', []);
        $commit = in_array('commit', $params, true);

        $conn = $this->em->getConnection();
        $conn->setNestTransactionsWithSavepoints(true);
        $conn->beginTransaction();

        try {
            $this->progress(sprintf('=== RECOVER INSTRUCTIONS %s — started ===', $commit ? 'COMMIT' : 'DRY-RUN'));

            $existing = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM `transaction` WHERE comment LIKE ?',
                [self::COMMENT_PREFIX . '%'],
            );
            if ($existing > 0) {
                $this->progress(sprintf('  %d recovered rows already exist.', $existing));
                if ($commit) {
                    throw new \RuntimeException('Recovery already run — aborting commit to avoid duplicates.');
                }
                $this->progress('  (dry-run continues for projection only)');
            }

            // PERIOD_IDS are **legacy** ids. migrateLegacy re-inserts periods with fresh
            // auto-increment ids, so 26 and 27 either do not exist or belong to a different
            // round; data/period_map.csv (written by `migrateLegacy commit`) translates them.
            // Keyed by the legacy id throughout, because that is what the CSV rows carry.
            $periods = [];
            foreach (self::PERIOD_IDS as $legacyId) {
                $id = $this->translatePeriodId($legacyId);
                $period = $this->em->find(Period::class, $id);
                if (!$period) {
                    throw new \RuntimeException(sprintf(
                        'Period %d (legacy %d) not found. If the ids differ, run migrateLegacy'
                            . ' commit first so data/period_map.csv exists.',
                        $id,
                        $legacyId,
                    ));
                }
                $periods[$legacyId] = $period;
            }

            $this->step('creating recovered transactions', fn () => $this->recover($periods));
            $this->step('flush', fn () => $this->em->flush());
            $this->step('back-date to period month/year', fn () => $this->applyTimestamps());

            if ($commit) {
                $conn->commit();
            } else {
                $conn->rollBack();
            }
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->progress('RECOVERY FAILED: ' . $e->getMessage());
            $this->progress($e->getTraceAsString());
            return $response;
        }

        $this->printReport($commit);

        return $response;
    }

    /**
     * Legacy period id => the new id it became, from data/period_map.csv.
     *
     * Falls through to the id as given when there is no map — correct when recovering into a
     * database whose period ids are already the legacy ones.
     */
    private function translatePeriodId(int $legacyId): int
    {
        if ($this->periodMap === null) {
            $this->periodMap = [];
            $file = DATA_PATH . '/period_map.csv';

            if (is_file($file) && ($fh = fopen($file, 'r')) !== false) {
                fgetcsv($fh, 0, ',', '"', '');
                while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                    if (isset($row[0], $row[1])) {
                        $this->periodMap[(int) $row[0]] = (int) $row[1];
                    }
                }
                fclose($fh);
                $this->progress(sprintf('period map loaded: %d entries', count($this->periodMap)));
            } else {
                $this->progress('no period map found — treating period ids as current ids');
            }
        }

        return $this->periodMap[$legacyId] ?? $legacyId;
    }

    /** @param array<int, Period> $periods */
    private function recover(array $periods): void
    {
        $file = DATA_PATH . '/' . self::CSV;
        if (!is_file($file)) {
            throw new \RuntimeException("Missing recovery file: $file");
        }

        $fh = fopen($file, 'r');
        fgetcsv($fh, 0, ',', '"', ''); // header: donor_email,beneficiary,amount,account,round,period

        $created = 0;
        $skipped = 0;
        $total = 0;
        $perPeriod = [];

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            [$email, , $amount, , $round, $periodId] = array_pad($row, 6, null);
            $email = trim((string) $email);
            $amount = (int) $amount;
            $periodId = (int) $periodId;

            $donor = $this->resolveDonor($email);
            if (!$donor) {
                $this->report['skipped'][] = "no donor for $email (round $round, $amount RSD)";
                $skipped++;
                continue;
            }
            $period = $periods[$periodId] ?? null;
            if (!$period) {
                $this->report['skipped'][] = "no period $periodId for $email (round $round)";
                $skipped++;
                continue;
            }

            $transaction = new Transaction();
            $transaction->donor = $donor;
            $transaction->beneficiary = null;                 // erased (GDPR)
            $transaction->project = $period->project;
            $transaction->period = $period;
            $transaction->paymentType = self::PAYMENT_TYPE_BANK;
            $transaction->accountNumber = null;               // redacted
            $transaction->instructions = null;                // redacted
            $transaction->amount = $amount;
            $transaction->amountEur = 0;
            // CONFIRMED ("Potvrdeno"), not PAID: both count as allocated money, but
            // PAID still carries a "proveriti sta je ovaj status" note in the entity and
            // these rows were being switched to CONFIRMED by hand after every import.
            $transaction->status = Transaction::STATUS_CONFIRMED;
            $transaction->comment = self::COMMENT_PREFIX . ' (round ' . $round . ')';
            $transaction->paymentCode = null;
            $this->em->persist($transaction);

            if ($period->month && $period->year) {
                $this->timestamps[] = [$transaction, sprintf('%04d-%02d-01 00:00:00', $period->year, $period->month)];
            }

            $created++;
            $total += $amount;
            $perPeriod[$periodId] = ($perPeriod[$periodId] ?? 0) + 1;
        }
        fclose($fh);

        foreach ($perPeriod as $pid => $n) {
            $this->addCount("period $pid: $n");
        }
        $this->addCount("created: $created");
        $this->addCount("skipped: $skipped");
        $this->addCount(sprintf('total recovered: %s RSD', number_format($total, 0, '.', ',')));
    }

    private function resolveDonor(string $email): ?Donor
    {
        if (!array_key_exists($email, $this->donorCache)) {
            $this->donorCache[$email] = $this->em->getRepository(Donor::class)->findOneBy(['email' => $email]);
        }
        return $this->donorCache[$email];
    }

    /** createdAt is insertable:false (DB default = now); overwrite it (and updatedAt) with the period's date. */
    private function applyTimestamps(): void
    {
        $conn = $this->em->getConnection();
        foreach ($this->timestamps as [$entity, $dt]) {
            $table = $this->em->getClassMetadata($entity::class)->getTableName();
            $conn->executeStatement(
                sprintf('UPDATE `%s` SET createdAt = :dt, updatedAt = :dt WHERE id = :id', $table),
                ['dt' => $dt, 'id' => $entity->getId()],
            );
        }
    }

    private function step(string $label, callable $fn): void
    {
        $this->progress(sprintf('[%s] %s ...', date('H:i:s'), $label));
        $fn();
    }

    private function addCount(string $line): void
    {
        $this->report['counts'][] = $line;
        $this->progress('  ' . $line);
    }

    private function printReport(bool $commit): void
    {
        $this->progress('');
        $this->progress('=== RECOVERY ' . ($commit ? 'COMMITTED' : 'DRY-RUN (rolled back)') . ' ===');
        foreach ($this->report['counts'] as $line) {
            $this->progress('  ' . $line);
        }
        $this->progress(sprintf('skipped rows: %d', count($this->report['skipped'])));
        foreach ($this->report['skipped'] as $line) {
            $this->progress('  ! ' . $line);
        }
    }

    private function progress(string $line): void
    {
        echo $line . PHP_EOL;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
