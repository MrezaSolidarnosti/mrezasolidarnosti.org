<?php

namespace Solidarity\Backend\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Entity\UserDelegateRequest;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Period\Entity\Period;
use Solidarity\School\Entity\City;
use Solidarity\School\Entity\School;
use Solidarity\School\Entity\SchoolType;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\User\Entity\User;

/**
 * One-off migration from the legacy Symfony app (solidaritySF) into the new
 * Skeletor schema.
 *
 * Run (dry-run, reports only, nothing committed):
 *     php public/cli.php migrateLegacy run
 * Run for real (commits):
 *     php public/cli.php migrateLegacy commit
 *
 * Reads the legacy database via a separate connection (creds in config under
 * `legacyDb`) and writes through the new Doctrine entities. Everything goes
 * into the single existing MSP project (id 1). Payment is bank-transfer only.
 *
 * Original created/updated timestamps are preserved: rows are inserted normally
 * (Timestampable has createdAt/updatedAt as insertable:false → DB defaults) and
 * then back-dated in a single raw-SQL pass (see applyTimestamps()).
 *
 * NOTE: legacy column names below assume Symfony's default snake_case naming.
 * Verify them against the actual dump (marked "VERIFY").
 *
 * A one-shot command that is deleted once the move to production is done, so it is left
 * untested on purpose and excluded from coverage — otherwise the largest file in the
 * codebase drags the figure down for work nobody intends to do.
 *
 * @codeCoverageIgnore
 */
class MigrateLegacy extends Html
{
    /** The legacy app is single-project; everything lands in the MSP project. */
    private const PROJECT_ID = 1;

    /**
     * Both project ids are fixed, not auto-increment, because the application hardcodes
     * them: DonorRepository::updateDonationData expands "both directions" to [1, 2], the
     * donation validator only accepts -1|1|2, TransactionRepository::PROJECT_MSPR is 2,
     * and the donate block's cards carry data-id="1"/"2". Seeding them here means the
     * mapping is guaranteed by the migration instead of depending on insertion order.
     */
    private const PROJECTS = [
        self::PROJECT_ID => ['code' => 'MSP', 'name' => 'Mreza solidarnosti za prosvetu'],
        2 => ['code' => 'MSPR', 'name' => 'Mreza solidarnosti protiv represije'],
    ];

    private const TYPE_BANK_TRANSFER = 1;
    private const CURRENCY_RSD = 1;

    /**
     * Rounds the recovery CSVs reference that solid_old does not contain, and the legacy ids
     * they call them by.
     *
     * The reconciliations that produced data/recovered_sf.csv and
     * data/recovered_instructions.csv ran against an **earlier snapshot** of the old system,
     * numbered differently from the dump we import today: that snapshot called February 2025
     * periods 26 and 27, and April 2025 period 20. April 2025 is not in solid_old's period
     * table at all, so without this the SF recovery has nowhere to put 5,539 transactions and
     * silently skips every one of them.
     *
     * Creating them here (and emitting the aliases into period_map.csv) is what makes the
     * whole sequence re-runnable. Doing it by hand cost an afternoon the first time, and a
     * forgotten alias fails quietly — the recovery reports "no period 20" and imports nothing.
     */
    private const RECOVERY_PERIODS = [
        20 => ['month' => 4, 'year' => 2025, 'type' => Period::TYPE_FULL],
        26 => ['month' => 2, 'year' => 2025, 'type' => Period::TYPE_FIRST_HALF],
        27 => ['month' => 2, 'year' => 2025, 'type' => Period::TYPE_SECOND_HALF],
    ];

    private Connection $legacy;

    /** @var array<string, array<int, object>> old id => new managed entity, per type */
    private array $map = [
        'city' => [], 'schoolType' => [], 'school' => [], 'period' => [],
        'donor' => [], 'delegate' => [], 'beneficiary' => [],
    ];

    /** old beneficiary id => its Period (so transactions can resolve the period) */
    private array $beneficiaryPeriod = [];

    /** @var array<int, Period> RECOVERY_PERIODS legacy id => the Period it resolves to */
    private array $recoveryAliases = [];

    /** old donor (user) id => total legacy transaction amount (one-time donor PM amount) */
    private array $transactionSumByDonor = [];

    /** @var array<int, array{0: object, 1: ?string, 2: ?string}> entity, createdAt, updatedAt */
    private array $timestamps = [];

    /**
     * `anomalies` is for things somebody has to look at; `notes` is for expected legacy
     * behaviour worth recording. Keeping top-up merges out of the anomaly list is the point
     * — there are enough of them to bury the handful of lines that actually need action.
     */
    private array $report = ['counts' => [], 'anomalies' => [], 'notes' => []];

    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        private EntityManagerInterface $em,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response,
    ) {
        // CliSkeletor passes argv tail as the "params" attribute; "commit" = real run.
        $params = (array) $request->getAttribute('params', []);
        $commit = in_array('commit', $params, true);

        $this->legacy = $this->openLegacyConnection();

        // Read-only reconciliation: did every legacy amount land on a period?
        if (in_array('verify', $params, true)) {
            $this->verifyMigration();
            return $response;
        }

        $conn = $this->em->getConnection();
        $conn->setNestTransactionsWithSavepoints(true);
        $conn->beginTransaction();

        try {
            $this->progress(sprintf(
                '=== LEGACY MIGRATION %s — started ===',
                $commit ? 'COMMIT' : 'DRY-RUN'
            ));
            $this->step('project', fn() => $this->ensureProject());
            $this->step('cities', fn() => $this->migrateCities());
            $this->step('school types', fn() => $this->migrateSchoolTypes());
            $this->step('schools', fn() => $this->migrateSchools());
            $this->step('periods', fn() => $this->migratePeriods());
            $this->step('users (admins/delegates/donors)', fn() => $this->migrateUsers());
            $this->step('school delegates', fn() => $this->assignSchoolDelegates());
            $this->step('delegate requests', fn() => $this->migrateUserDelegateRequests());
            $this->step('beneficiaries', fn() => $this->migrateBeneficiaries());
            $this->step('transactions', fn() => $this->migrateTransactions());

            $this->step('flush + restore original timestamps', function () {
                $this->em->flush();
                $this->applyTimestamps();          // restore original created/updated dates
            });

            if ($commit) {
                $conn->commit();
                $this->writePeriodMap();
            } else {
                $conn->rollBack();
            }
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->print('MIGRATION FAILED: ' . $e->getMessage());
            $this->print($e->getTraceAsString());
            return $response;
        }

        $this->printReport($commit);

        return $response;
    }

    /**
     * Reconcile legacy damaged_educator amounts against the new registered_periods.
     *
     * The totals are no longer expected to match. Dedup used to preserve the sum of duplicate
     * (person × period) rows, so any gap meant money had failed to land on a period. It now
     * drops repeated amounts and struck-out duplicates deliberately — one person had three
     * 50,000 rows for a single round and had asked for 50,000 — so the legacy side counts
     * requests the new side is right not to carry. Read the delta against the run's
     * "duplicate handling" counts rather than expecting zero.
     *
     * The invariant that replaced it is `paid <= requested` per (beneficiary, period):
     * whatever was dropped, money that actually moved must still have a request behind it.
     * Anything listed there is a real problem.
     *
     * Also lists registrations whose period is inactive: those are NOT missing (the FK is
     * NOT NULL), they just render as an empty "Izaberite Period" in the edit form, which
     * lists active periods only.
     */
    private function verifyMigration(): void
    {
        $legacy = $this->legacy->fetchAssociative(
            'SELECT COUNT(*) AS rows_count,
                    COALESCE(SUM(amount), 0) AS total,
                    COUNT(DISTINCT account_number) AS people,
                    COUNT(DISTINCT CONCAT(COALESCE(account_number, CONCAT("id:", id)), "|", period_id)) AS registrations
             FROM damaged_educator'
        );

        $new = $this->em->createQuery(
            'SELECT COUNT(rp.id) AS registrations, COALESCE(SUM(rp.amount), 0) AS total FROM ' . RegisteredPeriods::class . ' rp'
        )->getSingleResult();

        $inactive = $this->em->createQuery(
            'SELECT b.id AS beneficiaryId, b.name AS name, p.id AS periodId, rp.amount AS amount
             FROM ' . RegisteredPeriods::class . ' rp JOIN rp.period p JOIN rp.beneficiary b
             WHERE p.active = :active ORDER BY b.id'
        )->setParameter('active', false)->setMaxResults(50)->getResult();

        $legacyTotal = (int) $legacy['total'];
        $newTotal = (int) $new['total'];
        $shortfall = $legacyTotal - $newTotal;

        $this->print('=== BENEFICIARY AMOUNT → PERIOD RECONCILIATION ===');
        $this->print(sprintf('Legacy: %s rows, %s people, %s (person×period) registrations, total %s RSD',
            $legacy['rows_count'], $legacy['people'], $legacy['registrations'], number_format($legacyTotal)));
        $this->print(sprintf('New:    %s registered periods, total %s RSD',
            $new['registrations'], number_format($newTotal)));
        // A positive delta is now expected, not a failure. Repeated amounts, struck-out
        // duplicates and unfunded duplicate rows are dropped on purpose, so the legacy total
        // counts requests the new side deliberately does not. Compare the delta against the
        // "duplicate handling" counts from the run: the two should account for each other.
        // What must still hold is that nothing was PAID against a request that is missing —
        // the per-period check below.
        $this->print(sprintf('Amount delta (legacy − new): %s RSD %s',
            number_format($shortfall),
            $shortfall === 0
                ? '✓ every amount landed on a period'
                : 'ⓘ expected: duplicates and repeated amounts are dropped. Cross-check against the run\'s duplicate counts.'));

        // The invariant that actually matters: money delivered must have a request behind it.
        $overpaid = $this->em->createQuery(
            'SELECT b.id AS beneficiaryId, b.name AS name, p.id AS periodId, rp.amount AS requested,
                    (SELECT COALESCE(SUM(t2.amount), 0) FROM ' . Transaction::class . ' t2
                     WHERE t2.beneficiary = b AND t2.period = p AND t2.status IN (1, 2, 3, 7)) AS paid
             FROM ' . RegisteredPeriods::class . ' rp JOIN rp.period p JOIN rp.beneficiary b
             ORDER BY b.id'
        )->getResult();
        $overpaid = array_filter($overpaid, static fn (array $r): bool => (int) $r['paid'] > (int) $r['requested']);

        $this->print(sprintf('Registrations where paid exceeds requested: %d %s',
            count($overpaid),
            $overpaid === [] ? '✓' : '⚠ these received more than the migrated request'));
        foreach (array_slice($overpaid, 0, 50) as $r) {
            $this->print(sprintf('  beneficiary #%d "%s" — period #%d, requested %s, paid %s',
                $r['beneficiaryId'], $r['name'], $r['periodId'],
                number_format((int) $r['requested']), number_format((int) $r['paid'])));
        }

        $this->print(sprintf('Registrations on INACTIVE periods: %d (these look empty in the form but the period is set).', count($inactive)));
        foreach ($inactive as $r) {
            $this->print(sprintf('  beneficiary #%d "%s" — period #%d, amount %s RSD',
                $r['beneficiaryId'], $r['name'], $r['periodId'], number_format((int) $r['amount'])));
        }
    }

    // ---- phases ----------------------------------------------------------

    private function migrateCities(): void
    {
        foreach ($this->legacy->fetchAllAssociative('SELECT id, name, created_at, updated_at FROM city') as $row) {
            $city = new City();
            $city->name = $row['name'];
            $this->persist($city, $row);
            $this->map['city'][(int) $row['id']] = $city;
        }
        $this->em->flush();
        $this->count('cities', $this->map['city']);
    }

    private function migrateSchoolTypes(): void
    {
        foreach ($this->legacy->fetchAllAssociative('SELECT id, name, created_at, updated_at FROM school_type') as $row) {
            $type = new SchoolType();
            $type->name = $row['name'];
            $this->persist($type, $row);
            $this->map['schoolType'][(int) $row['id']] = $type;
        }
        $this->em->flush();
        $this->count('schoolTypes', $this->map['schoolType']);
    }

    private function migrateSchools(): void
    {
        // VERIFY columns: type_id, city_id
        $sql = 'SELECT id, name, type_id, city_id, created_at, updated_at FROM school';
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $school = new School();
            $school->name = $row['name'];
            $school->city = $this->map['city'][(int) $row['city_id']];
            // School::$type is not nullable; a legacy row with no usable type is adopted by
            // the placeholder rather than lost, along with the beneficiaries hanging off it.
            $school->type = (isset($row['type_id']) ? ($this->map['schoolType'][(int) $row['type_id']] ?? null) : null)
                ?? $this->placeholderSchoolType();
            $school->delegate = null; // assigned later from user_delegate_school
            $this->persist($school, $row);
            $this->map['school'][(int) $row['id']] = $school;
        }
        $this->em->flush();
        $this->count('schools', $this->map['school']);
    }

    private function migratePeriods(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $sql = 'SELECT id, month, year, type, active, processing, created_at, updated_at FROM damaged_educator_period';
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $period = new Period();
            $period->project = $project;
            $period->month = (int) $row['month'];
            $period->year = (int) $row['year'];
            $period->type = $row['type'];
            $period->active = (bool) $row['active'];
            $period->processing = (bool) $row['processing'];
            $period->maxAmount = 0; // legacy has no per-period max
            $this->persist($period, $row);
            $this->map['period'][(int) $row['id']] = $period;
        }
        $this->em->flush();
        $this->count('periods', $this->map['period']);
        $this->ensureRecoveryPeriods($project);
    }

    /**
     * Create any RECOVERY_PERIODS round the legacy dump does not have, and record the alias
     * so period_map.csv can translate the recovery CSVs' ids onto it.
     *
     * Matched on month/year/type rather than created blindly: February 2025 already exists in
     * solid_old, so those two aliases point at the migrated rows and only April 2025 is new.
     */
    private function ensureRecoveryPeriods(Project $project): void
    {
        $created = 0;

        foreach (self::RECOVERY_PERIODS as $legacyId => $spec) {
            $existing = $this->em->getRepository(Period::class)->findOneBy([
                'month' => $spec['month'], 'year' => $spec['year'], 'type' => $spec['type'], 'project' => $project,
            ]);

            if ($existing) {
                $this->recoveryAliases[$legacyId] = $existing;
                continue;
            }

            $period = new Period();
            $period->project = $project;
            $period->month = $spec['month'];
            $period->year = $spec['year'];
            $period->type = $spec['type'];
            // Inactive: a closed historical round must not appear as open for registration.
            $period->active = false;
            $period->processing = false;
            $period->maxAmount = 0;
            $this->persist($period, ['created_at' => sprintf('%04d-%02d-01 00:00:00', $spec['year'], $spec['month'])]);
            $this->recoveryAliases[$legacyId] = $period;
            $created++;

            $this->report['notes'][] = sprintf(
                'Created recovery period %04d-%02d (%s) — referenced by the recovery CSVs as'
                    . ' legacy id %d but absent from the legacy dump.',
                $spec['year'],
                $spec['month'],
                $spec['type'],
                $legacyId,
            );
        }

        $this->em->flush();
        $this->addCount(sprintf(
            'recovery periods: %d created, %d matched to existing rounds',
            $created,
            count(self::RECOVERY_PERIODS) - $created,
        ));
    }

    private function migrateUsers(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $phoneByUser = $this->legacyPhoneByUser();

        // VERIFY: user.roles is JSON; user_donor(user_id, amount, school_type, comment, created_at, updated_at)
        $sql = 'SELECT u.id, u.email, u.roles, u.first_name, u.last_name, u.is_active, u.is_email_verified,
                       u.created_at, u.updated_at, u.last_visit,
                       ud.amount AS donor_amount, ud.school_type AS donor_school_type, ud.comment AS donor_comment,
                       ud.created_at AS donor_created_at, ud.updated_at AS donor_updated_at
                FROM user u
                LEFT JOIN user_donor ud ON ud.user_id = u.id';

        // A donor is anyone who pledged (user_donor = monthly) OR who actually transacted
        // (one-time). The per-donor transaction sum also feeds one-time payment methods.
        $this->transactionSumByDonor = $this->legacyTransactionSumByDonor();

        $admins = $delegates = $donors = 0;
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $roles = json_decode((string) $row['roles'], true) ?: [];
            $oldId = (int) $row['id'];

            if (in_array('ROLE_ADMIN', $roles, true)) {
                $this->persist($this->buildAdmin($row), $row);
                $admins++;
            }
            if (in_array('ROLE_DELEGATE', $roles, true)) {
                $delegate = $this->buildDelegate($row, $phoneByUser[$oldId] ?? '', $project);
                $this->persist($delegate, $row);
                $this->map['delegate'][$oldId] = $delegate;
                $delegates++;
            }
            if ($row['donor_amount'] !== null || isset($this->transactionSumByDonor[$oldId])) {
                $donor = $this->buildDonor($row, $project);
                $this->persist($donor, $row);
                $this->map['donor'][$oldId] = $donor;
                $donors++;
            }
        }
        $this->em->flush();
        $this->addCount("admins: $admins, delegates: $delegates, donors: $donors");
    }

    private function assignSchoolDelegates(): void
    {
        $bySchool = [];
        foreach ($this->legacy->fetchAllAssociative('SELECT user_id, school_id FROM user_delegate_school') as $row) {
            $bySchool[(int) $row['school_id']][] = (int) $row['user_id'];
        }

        $assigned = 0;
        foreach ($bySchool as $oldSchoolId => $userIds) {
            $school = $this->map['school'][$oldSchoolId] ?? null;
            if (!$school) {
                continue;
            }
            $userIds = array_values(array_unique($userIds));
            if (count($userIds) > 1) {
                $this->report['anomalies'][] = sprintf(
                    'School #%d (%s) has %d delegates: %s — assigned the first.',
                    $oldSchoolId, $school->name, count($userIds), implode(',', $userIds),
                );
            }
            $delegate = $this->map['delegate'][$userIds[0]] ?? null;
            if ($delegate) {
                $school->delegate = $delegate;
                $assigned++;
            }
        }
        $this->em->flush();
        $this->addCount("schools with delegate: $assigned");
    }

    private function migrateUserDelegateRequests(): void
    {
        // Near 1:1. The new entity drops the legacy user/schoolType/city FKs.
        // VERIFY columns: first_name, last_name, school_id, total_educators,
        //                 total_blocked_educators, admin_comment
        $count = 0;
        foreach ($this->legacy->fetchAllAssociative('SELECT * FROM user_delegate_request') as $row) {
            $req = new UserDelegateRequest();
            $req->firstName = (string) $row['first_name'];
            $req->lastName = (string) $row['last_name'];
            $req->phone = $row['phone'] ?? null;
            $req->school = isset($row['school_id']) ? ($this->map['school'][(int) $row['school_id']] ?? null) : null;
            $req->totalEducators = isset($row['total_educators']) ? (int) $row['total_educators'] : null;
            $req->totalBlockedEducators = isset($row['total_blocked_educators']) ? (int) $row['total_blocked_educators'] : null;
            $req->comment = $row['comment'] ?? null;
            $req->status = (int) $row['status']; // NEW/CONFIRMED/REJECTED identical in both apps
            $req->adminComment = $row['admin_comment'] ?? null;
            $this->persist($req, $row);
            $count++;
        }
        $this->em->flush();
        $this->addCount("delegate requests: $count");
    }

    private function migrateBeneficiaries(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        // The legacy app stores one damaged_educator row per (person × period): the same
        // person recurs across periods with the same account_number. The new model is one
        // Beneficiary + many RegisteredPeriods + one PaymentMethod, so we group the old rows
        // by person and merge. Ordered oldest-first so the earliest row is the representative
        // (its timestamps backdate the Beneficiary/PaymentMethod).
        // VERIFY columns: school_id, account_number, status_comment, created_by_id, period_id
        $sql = 'SELECT id, name, school_id, amount, account_number, status, status_comment, created_by_id, period_id,
                       created_at, updated_at
                FROM damaged_educator
                ORDER BY created_at ASC, id ASC';

        /** @var array<string, array{beneficiary: Beneficiary, periods: array<int, RegisteredPeriods>}> $groups */
        $groups = [];

        // Accounts that identify an institution or a mistake rather than a person.
        $nonIdentifying = $this->nonIdentifyingAccounts();
        $skippedDuplicates = 0;
        $fundedDuplicates = 0;
        $repeatedRows = 0;
        $deletedKept = 0;

        // What was actually paid against each legacy row, so a struck-out duplicate that
        // already received money keeps a request behind that payment.
        $paidByRow = [];
        // Statuses 1,2,3,7 = NEW, WAITING_CONFIRMATION, CONFIRMED, PAID — the same set
        // TransactionRepository::getAllocatedStatuses() uses to decide what counts against a
        // beneficiary, and identical in both apps. CANCELLED, NOT_PAID and EXPIRED are
        // excluded on purpose: an instruction that was never honoured is not money received,
        // and counting it would keep a registration alive for a duplicate that was only ever
        // cancelled.
        $paidSql = 'SELECT damaged_educator_id, COALESCE(SUM(amount), 0) AS paid
                    FROM transaction
                    WHERE damaged_educator_id IS NOT NULL
                      AND status IN (1, 2, 3, 7)
                    GROUP BY damaged_educator_id';
        foreach ($this->legacy->fetchAllAssociative($paidSql) as $paidRow) {
            $paidByRow[(int) $paidRow['damaged_educator_id']] = (int) $paidRow['paid'];
        }

        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $oldId = (int) $row['id'];
            $account = trim((string) ($row['account_number'] ?? ''));
            // An account only counts as an identity if it belongs to exactly one person.
            $accountIdentifies = $account !== '' && !isset($nonIdentifying[$account]);
            // Identity key: the bank account (same person across periods). Account-less rows
            // (legacy is bank-transfer only, so these are rare) and rows carrying a shared or
            // budget account fall back to name + school.
            $key = $accountIdentifies
                ? 'acc:' . $account
                : 'ns:' . mb_strtolower(trim($row['name'])) . '|' . (int) ($row['school_id'] ?? 0);

            if (!isset($groups[$key])) {
                $beneficiary = new Beneficiary();
                $beneficiary->name = $row['name'];
                $beneficiary->status = (int) $row['status']; // NEW=1/DELETED=2 identical in both apps
                $beneficiary->comment = $row['status_comment'] ?? null;
                $beneficiary->school = isset($row['school_id']) ? ($this->map['school'][(int) $row['school_id']] ?? null) : null;
                $beneficiary->createdBy = isset($row['created_by_id']) ? ($this->map['delegate'][(int) $row['created_by_id']] ?? null) : null;
                $this->persist($beneficiary, $row);

                // One bank-transfer payment method per person (account moved off the educator).
                //
                // Deliberately skipped for a non-identifying account. Copying a shared or
                // budget account onto each of these people would put the same accountNumber
                // on several beneficiaries, and Beneficiary\Validator refuses to save any of
                // them after that ("Broj računa … je već dodeljen") — they would arrive
                // uneditable. Better to land with no account and be listed for manual entry.
                if ($accountIdentifies) {
                    $pm = new BeneficiaryPaymentMethod();
                    $pm->beneficiary = $beneficiary;
                    $pm->type = self::TYPE_BANK_TRANSFER;
                    $pm->accountNumber = $account;
                    $pm->wireInstructions = null;
                    $this->persist($pm, $row);
                } elseif ($account !== '') {
                    $this->report['anomalies'][] = sprintf(
                        'Beneficiary row #%d (%s) carried non-identifying account %s — imported'
                            . ' with no payment method, needs a real account before they can be paid.',
                        $oldId,
                        $row['name'],
                        $account,
                    );
                }

                $groups[$key] = ['beneficiary' => $beneficiary, 'periods' => [], 'amounts' => [], 'comments' => []];
            }

            $beneficiary = $groups[$key]['beneficiary'];
            // A person is active if any of their period-rows is active.
            if ((int) $row['status'] === Beneficiary::STATUS_NEW) {
                $beneficiary->status = Beneficiary::STATUS_NEW;
            }

            // Every distinct comment across the person's rows, joined. The legacy app writes
            // one per (person × period), so taking only the first would drop the reason
            // somebody was struck out or removed — which is usually written on a later row
            // than the oldest. Distinct, because the same note is often repeated verbatim
            // across a person's periods.
            $comment = trim((string) ($row['status_comment'] ?? ''));
            if ($comment !== '' && !in_array($comment, $groups[$key]['comments'], true)) {
                $groups[$key]['comments'][] = $comment;
                $beneficiary->comment = implode(' | ', $groups[$key]['comments']);
            }

            // Every old id maps to the merged beneficiary so transactions still resolve.
            $this->map['beneficiary'][$oldId] = $beneficiary;

            // Registered period (amount + period moved off the educator).
            $periodOldId = isset($row['period_id']) ? (int) $row['period_id'] : 0;
            $period = $periodOldId ? ($this->map['period'][$periodOldId] ?? null) : null;
            if (!$period) {
                $this->report['anomalies'][] = "Beneficiary row #$oldId ({$row['name']}) has no period — skipped registered amount.";
                continue;
            }

            // Resolved before the deleted check below, and on purpose: this mapping is how
            // migrateTransactions() finds a transaction's period, so it has to exist for
            // every row including the deleted ones. Skipping it is what stranded most of the
            // transactions with "period=MISSING" — the money was paid against a row that was
            // later struck out, and dropping the mapping dropped the payment with it.
            $this->beneficiaryPeriod[$oldId] = $period;

            $amount = (int) $row['amount'];
            $isDeleted = (int) $row['status'] === Beneficiary::STATUS_DELETED;

            // A row struck out as a duplicate normally carries no registration: counting it
            // would add the struck-out amount back onto the live one and the person would
            // arrive asking for the same round twice over.
            //
            // Unless money actually moved against it. Some struck-out rows were paid before
            // anyone noticed the duplication, and dropping their amount would leave the new
            // database showing funds delivered against a request that is not there — paid
            // exceeding requested, which nothing downstream expects. So a funded duplicate
            // keeps exactly what was **paid**, not what it asked for: enough to keep the
            // books consistent, without reinstating the inflated request.
            //
            // Deletion alone is never the test. A deleted row is usually a real person whose
            // request genuinely existed; they are kept, deleted status and all, the same way
            // Statistics counts them for transparency.
            if ($isDeleted && $this->looksLikeDuplicate($row['status_comment'] ?? null)) {
                $paid = $paidByRow[$oldId] ?? 0;

                if ($paid <= 0) {
                    $skippedDuplicates++;
                    $this->report['notes'][] = sprintf(
                        'Beneficiary row #%d (%s) struck out as a duplicate ("%s"), never funded'
                            . ' — no registered amount taken.',
                        $oldId,
                        $row['name'],
                        trim((string) $row['status_comment']),
                    );
                    continue;
                }

                $this->report['anomalies'][] = sprintf(
                    'Beneficiary row #%d (%s) struck out as a duplicate ("%s") but %d was already'
                        . ' paid against it — registering %d (the amount paid) instead of the %d it'
                        . ' asked for, so the payment still has a request behind it.',
                    $oldId,
                    $row['name'],
                    trim((string) $row['status_comment']),
                    $paid,
                    $paid,
                    $amount,
                );
                $fundedDuplicates++;
                $amount = $paid;
            } elseif ($isDeleted) {
                $deletedKept++;
            }

            if (isset($groups[$key]['periods'][$periodOldId])) {
                $registration = $groups[$key]['periods'][$periodOldId];

                // A repeat of an amount already registered for this round is the same request
                // entered twice, not a second one. Miloš Vojnović has three 50,000 rows in
                // period 2 and asked for 50,000: summing them registered 150,000 against
                // ~45,000 actually paid. Identical amount => drop it.
                if (isset($groups[$key]['amounts'][$periodOldId][$amount])) {
                    $repeatedRows++;
                    $this->report['notes'][] = sprintf(
                        'Beneficiary row #%d (%s) repeats %d for period #%d — already registered, not added again.',
                        $oldId,
                        $row['name'],
                        $amount,
                        $periodOldId,
                    );
                    continue;
                }

                // A different amount is a genuine second entry — the top-up case — so the
                // amounts do belong together. These are rare once the repeats are gone, and
                // each one is listed so the remainder can be eyeballed rather than trusted.
                $registration->amount += $amount;
                $groups[$key]['amounts'][$periodOldId][$amount] = true;

                // Only worth flagging if the total lands somewhere a single request never
                // could. Nothing enforces the cap on this path (the migration writes entities
                // directly, bypassing the validators), so an over-limit registration would go
                // in silently and only surface later in allocation.
                if ($registration->amount > Beneficiary::MONTHLY_LIMIT) {
                    $this->report['anomalies'][] = sprintf(
                        'Beneficiary row #%d (%s) tops up period #%d to %d, above the %d limit — check before paying.',
                        $oldId,
                        $row['name'],
                        $periodOldId,
                        $registration->amount,
                        Beneficiary::MONTHLY_LIMIT,
                    );
                } else {
                    $this->report['anomalies'][] = sprintf(
                        'Beneficiary row #%d (%s) adds %d to period #%d, making %d — a second entry'
                            . ' for a different amount, not a repeat. Check it is a real top-up.',
                        $oldId,
                        $row['name'],
                        $amount,
                        $periodOldId,
                        $registration->amount,
                    );
                }
            } else {
                $rp = new RegisteredPeriods();
                $rp->beneficiary = $beneficiary;
                $rp->project = $project;
                $rp->period = $period;
                $rp->amount = $amount;
                $this->persist($rp, $row);
                $groups[$key]['periods'][$periodOldId] = $rp;
                $groups[$key]['amounts'][$periodOldId][$amount] = true;
            }
        }

        $this->em->flush();
        $this->addCount(sprintf('beneficiaries: %d (from %d legacy rows)', count($groups), count($this->map['beneficiary'])));
        $this->addCount(sprintf(
            'duplicate handling: %d repeated amounts dropped, %d unfunded struck-out duplicates'
                . ' dropped, %d funded struck-out duplicates registered at the amount paid,'
                . ' %d other deleted rows kept in full',
            $repeatedRows,
            $skippedDuplicates,
            $fundedDuplicates,
            $deletedKept,
        ));
    }

    /**
     * Whether a legacy status comment marks the row as a duplicate.
     *
     * Hand-typed, so it turns up as "duplikat", "Duplikat", "duplikat unosa", "duplirano",
     * "duplo", the English "duplicate" and the Cyrillic "дупликат". Matching the stem covers
     * all of them without trying to enumerate spellings.
     *
     * Deliberately broad, because the cost is asymmetric: a missed duplicate silently doubles
     * someone's request, while a false match only drops an amount that is listed in the
     * report with its comment quoted — so a wrong call is visible and correctable, and the
     * money is untouched either way.
     */
    private function looksLikeDuplicate(?string $comment): bool
    {
        $text = mb_strtolower(trim((string) $comment));

        return $text !== '' && (str_contains($text, 'dupl') || str_contains($text, 'дупл'));
    }

    private function migrateTransactions(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        // VERIFY columns: damaged_educator_id, account_number, user_donor_confirmed
        $sql = 'SELECT id, user_id, damaged_educator_id, account_number, amount, status, user_donor_confirmed,
                       created_at, updated_at
                FROM transaction';

        $migrated = $skipped = 0;
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $donor = isset($row['user_id']) ? ($this->map['donor'][(int) $row['user_id']] ?? null) : null;
            $beneficiary = isset($row['damaged_educator_id']) ? ($this->map['beneficiary'][(int) $row['damaged_educator_id']] ?? null) : null;
            $period = $this->beneficiaryPeriod[(int) ($row['damaged_educator_id'] ?? 0)] ?? null;

            if (!$donor || !$beneficiary || !$period) {
                $this->report['anomalies'][] = sprintf(
                    'Transaction #%d skipped (donor=%s beneficiary=%s period=%s).',
                    $row['id'], $donor ? 'ok' : 'MISSING', $beneficiary ? 'ok' : 'MISSING', $period ? 'ok' : 'MISSING',
                );
                $skipped++;
                continue;
            }

            $t = new Transaction();
            $t->donor = $donor;
            $t->beneficiary = $beneficiary;
            $t->project = $project;
            $t->period = $period;
            $t->paymentType = self::TYPE_BANK_TRANSFER;
            $t->accountNumber = $row['account_number'] ?? null;
            $t->instructions = null;
            $t->amount = (int) $row['amount'];
            $t->amountEur = 0;
            $t->status = (int) $row['status']; // status constants identical in both apps
            $t->comment = null;
            $t->paymentCode = null;
            $this->persist($t, $row);
            $migrated++;
        }
        $this->em->flush();
        $this->addCount("transactions: migrated $migrated, skipped $skipped");
    }

    // ---- entity builders -------------------------------------------------

    private function buildAdmin(array $row): User
    {
        $user = new User();
        $user->email = $row['email'];
        $user->firstName = (string) $row['first_name'];
        $user->lastName = (string) $row['last_name'];
        $user->displayName = trim($row['first_name'] . ' ' . $row['last_name']) ?: $row['email'];
        $user->role = User::ROLE_ADMIN;
        $user->isActive = (int) ((bool) $row['is_active']);
        $user->ipv4 = null;
        $user->lastLogin = null;

        return $user;
    }

    private function buildDelegate(array $row, string $phone, object $project): Delegate
    {
        $delegate = new Delegate();
        $delegate->email = $row['email'];
        $delegate->name = trim($row['first_name'] . ' ' . $row['last_name']) ?: $row['email'];
        $delegate->status = ((bool) $row['is_active']) ? Delegate::STATUS_VERIFIED : Delegate::STATUS_NEW;
        $delegate->phone = $phone;
        $delegate->verifiedBy = 'legacy-migration';
        $delegate->comment = null;
        $delegate->adminComment = null;
        $delegate->ipv4 = null;
        $delegate->lastLogin = null;
        $delegate->projects->add($project);

        return $delegate;
    }

    private function buildDonor(array $row, object $project): Donor
    {
        $donor = new Donor();
        $donor->email = $row['email'];
        $donor->firstName = (string) $row['first_name'];
        $donor->lastName = (string) $row['last_name'];
        $donor->status = ((bool) $row['is_email_verified']) ? Donor::STATUS_VERIFIED : Donor::STATUS_NEW;
        // school_type values are aligned 1:1 (ALL=1, UNI=2, SCHOOL=3) — see Donor constants.
        $donor->wantsToDonateTo = (int) ($row['donor_school_type'] ?? Donor::DONATE_TO_ALL);
        $donor->comment = $row['donor_comment'] ?? null;
        $donor->isActive = (string) (int) ((bool) $row['is_active']);
        $donor->ipv4 = null;
        $donor->lastLogin = null;
        // Legacy user.last_visit was stamped on every authenticated request, which is exactly
        // what our lastVisit means — carry it over so ExpireInstructions can tell "never came
        // back" from "came back and did not pay" for migrated donors on the very first run.
        // lastLogin stays null: the legacy app tracked no separate login timestamp.
        $donor->lastVisit = !empty($row['last_visit']) ? new \DateTime((string) $row['last_visit']) : null;

        // Monthly vs one-time is structural in the legacy app: a `user_donor` row means a
        // monthly subscription (/mesecna-donacija created it, /odjava-… deleted it); the
        // one-time flow (/jednokratna-donacija) created transactions directly, no user_donor.
        $oldId = (int) $row['id'];
        if ($row['donor_amount'] !== null) {
            // Monthly donor — amount is the monthly pledge (user_donor.amount).
            $pm = new DonorPaymentMethod();
            $pm->donor = $donor;
            $pm->project = $project;
            $pm->type = self::TYPE_BANK_TRANSFER;
            $pm->amount = (int) $row['donor_amount'];
            $pm->monthly = 1;
            $pm->currency = self::CURRENCY_RSD;
            $donor->paymentMethods->add($pm);
            // stamp the payment method with the donor profile's (user_donor) own timestamps
            $this->persist($pm, ['created_at' => $row['donor_created_at'] ?? null, 'updated_at' => $row['donor_updated_at'] ?? null]);
        } elseif (($this->transactionSumByDonor[$oldId] ?? 0) > 0) {
            // One-time donor — no subscription; amount is the sum of their legacy transactions.
            $pm = new DonorPaymentMethod();
            $pm->donor = $donor;
            $pm->project = $project;
            $pm->type = self::TYPE_BANK_TRANSFER;
            $pm->amount = $this->transactionSumByDonor[$oldId];
            $pm->monthly = 0;
            $pm->currency = self::CURRENCY_RSD;
            $donor->paymentMethods->add($pm);
            $this->persist($pm, ['created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null]);
        }

        // donor_project is a separate join table from the payment methods, and
        // DonorRepository::updateDonationData() writes BOTH whenever someone donates through
        // the app — clearing and rebuilding them together. Populating only the payment methods
        // here left every migrated donor with an empty collection, which is what renders as
        // the bare "()" beside their email in the donor list.
        if (!$donor->paymentMethods->isEmpty()) {
            $donor->projects->add($project);
        }

        return $donor;
    }

    // ---- helpers ---------------------------------------------------------

    /**
     * Persist a new entity and remember its source row's timestamps so they can
     * be restored after flush.
     *
     * @param array<string, mixed> $row source row with created_at / updated_at
     */
    private function persist(object $entity, array $row): void
    {
        $this->em->persist($entity);
        $this->timestamps[] = [$entity, $row['created_at'] ?? null, $row['updated_at'] ?? null];
    }

    /**
     * Restore the legacy created/updated timestamps. createdAt/updatedAt are
     * insertable:false, so they default to "now" on insert; here we overwrite
     * them with raw SQL. Both are set in one statement so the ON UPDATE
     * CURRENT_TIMESTAMP on updatedAt doesn't bump it back to now.
     */
    private function applyTimestamps(): void
    {
        $conn = $this->em->getConnection();
        foreach ($this->timestamps as [$entity, $createdAt, $updatedAt]) {
            if (!$createdAt) {
                continue;
            }
            $table = $this->em->getClassMetadata($entity::class)->getTableName();
            $conn->executeStatement(
                sprintf('UPDATE `%s` SET createdAt = :createdAt, updatedAt = :updatedAt WHERE id = :id', $table),
                ['createdAt' => $createdAt, 'updatedAt' => $updatedAt ?: $createdAt, 'id' => $entity->getId()],
            );
        }
    }

    /** @return array<int, true> set of old user ids referenced as a transaction donor */
    /**
     * Total legacy transaction amount per donor (old user_id). Keys double as "this donor
     * transacted", and the value is the one-time donor's payment-method amount.
     *
     * @return array<int,int>
     */
    private function legacyTransactionSumByDonor(): array
    {
        $sums = [];
        foreach ($this->legacy->fetchAllAssociative('SELECT user_id, SUM(amount) AS total FROM transaction WHERE user_id IS NOT NULL GROUP BY user_id') as $row) {
            $sums[(int) $row['user_id']] = (int) $row['total'];
        }
        return $sums;
    }

    /** @return array<int, string> old user id => phone (from the un-migrated delegate request) */
    private function legacyPhoneByUser(): array
    {
        $out = [];
        foreach ($this->legacy->fetchAllAssociative('SELECT user_id, phone FROM user_delegate_request WHERE phone IS NOT NULL') as $row) {
            $out[(int) $row['user_id']] = (string) $row['phone'];
        }
        return $out;
    }

    /**
     * Seed both projects on their fixed ids (see self::PROJECTS). The import itself only
     * uses MSP (id 1) — the legacy app was single-project — but MSPR has to exist on id 2
     * or the hardcoded ids across the app point at the wrong direction of support, which
     * silently saves donors to the project they did not choose.
     *
     * Idempotent per id. A row that already holds the id with a different code is left
     * alone and reported: renumbering it here would orphan every row referencing it, so
     * that is a decision for a human, not a migration.
     */
    /**
     * Hand the legacy → new period id mapping to the recovery commands.
     *
     * Periods are inserted with `new Period()`, so they get fresh auto-increment ids and the
     * legacy numbering is lost the moment this process exits. Both recovery commands are
     * keyed on legacy ids — recovered_sf.csv carries one per row, RecoverInstructions names
     * 26 and 27 — and both resolve them with a plain find(), which after a fresh migration
     * either misses or, worse, hits a different period.
     *
     * They fail safely (the row is skipped and reported) rather than writing to the wrong
     * round, but a recovery that silently does nothing is its own problem. Writing the map
     * out here means nobody has to hand-edit a CSV of donation data to translate it.
     *
     * Only on commit: a dry run rolls back, so its ids describe rows that no longer exist.
     */
    private function writePeriodMap(): void
    {
        $lines = ['legacy_id,new_id'];
        foreach ($this->map['period'] as $legacyId => $period) {
            $lines[] = $legacyId . ',' . $period->getId();
        }

        // The recovery CSVs' own ids, from the earlier snapshot — appended last so they win
        // if one ever collides with a solid_old id.
        foreach ($this->recoveryAliases as $legacyId => $period) {
            $lines[] = $legacyId . ',' . $period->getId();
        }

        $file = DATA_PATH . '/period_map.csv';
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
        $this->print(sprintf(
            'period map written: %s (%d periods + %d recovery aliases)',
            $file,
            count($this->map['period']),
            count($this->recoveryAliases),
        ));
    }

    private function ensureProject(): void
    {
        $conn = $this->em->getConnection();

        foreach (self::PROJECTS as $id => $project) {
            $existingCode = $conn->fetchOne('SELECT code FROM project WHERE id = ?', [$id]);

            if ($existingCode !== false) {
                if ($existingCode !== $project['code']) {
                    $this->addCount(sprintf(
                        'project: WARNING id %d holds code "%s", expected "%s" — the app hardcodes these ids, fix before going live',
                        $id,
                        $existingCode,
                        $project['code']
                    ));
                }
                continue;
            }

            // A code sitting on the wrong id is the failure this method exists to prevent.
            $misplacedId = $conn->fetchOne('SELECT id FROM project WHERE code = ?', [$project['code']]);
            if ($misplacedId !== false) {
                $this->addCount(sprintf(
                    'project: WARNING %s exists on id %s but the app expects id %d — not creating a duplicate',
                    $project['code'],
                    $misplacedId,
                    $id
                ));
                continue;
            }

            $conn->executeStatement(
                'INSERT INTO project (id, name, code, logo) VALUES (?, ?, ?, ?)',
                [$id, $project['name'], $project['code'], ''],
            );
            $this->addCount(sprintf('project: created %s (id %d)', $project['code'], $id));
        }
    }

    private function openLegacyConnection(): Connection
    {
        $db = $this->getConfig()->offsetGet('legacyDb');

        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $db->host,
            'dbname' => $db->name,
            'user' => $db->user,
            'password' => $db->pass,
        ]);
    }

    private function count(string $label, array $map): void
    {
        $this->addCount(sprintf('%s: %d', $label, count($map)));
    }

    /**
     * Record a count line for the final report and echo it immediately so the
     * console shows progress while the (long) migration runs.
     */
    private function addCount(string $line): void
    {
        $this->report['counts'][] = $line;
        $this->progress('  ' . $line);
    }

    /** Announce a phase (timestamped, flushed) and run it. */
    private function step(string $label, callable $fn): void
    {
        $this->progress(sprintf('[%s] %s ...', date('H:i:s'), $label));
        $fn();
    }

    /**
     * Print a line and force it out now. CLI output buffering (on by default in
     * lsphp) otherwise holds everything until the script ends, so we flush both
     * the output buffer (if any) and the SAPI after every line.
     */
    private function progress(string $line): void
    {
        echo $line . PHP_EOL;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    private function printReport(bool $commit): void
    {
        $header = '=== LEGACY MIGRATION ' . ($commit ? 'COMMITTED' : 'DRY-RUN (rolled back)') . ' ===';

        // Full detail (counts + every anomaly) goes to a file so nothing scrolls off.
        $lines = [$header];
        foreach ($this->report['counts'] as $line) {
            $lines[] = '  ' . $line;
        }
        $lines[] = '--- anomalies (' . count($this->report['anomalies']) . ') ---';
        foreach ($this->report['anomalies'] as $line) {
            $lines[] = '  ! ' . $line;
        }
        $lines[] = '--- notes, expected legacy behaviour (' . count($this->report['notes']) . ') ---';
        foreach ($this->report['notes'] as $line) {
            $lines[] = '  - ' . $line;
        }
        $file = DATA_PATH . '/migration-report.txt';
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);

        // Console gets the summary only.
        $this->print('');
        $this->print($header);
        foreach ($this->report['counts'] as $line) {
            $this->print('  ' . $line);
        }
        $this->print(sprintf(
            'anomalies: %d   notes: %d  (full report: %s)',
            count($this->report['anomalies']),
            count($this->report['notes']),
            $file,
        ));
    }

    private function print(string $line): void
    {
        echo $line . PHP_EOL;
    }

    /**
     * Account numbers that cannot identify a person, so must not be used to group rows.
     *
     * The identity key is normally the bank account, because the legacy app repeats the same
     * account for one person across periods. That breaks when the account is institutional:
     * 840000000000162021 (a Republic budget account) appears against 3 people in one period
     * and 9 in another, and keying on it folds all of them into a single beneficiary,
     * dragging every one of their transactions along.
     *
     * Only the 840 prefix is rejected, and deliberately so. Two earlier attempts inferred
     * shared accounts from the names attached to them, and both drowned the report in false
     * positives: one person spelled two ways ("Jasmina Jeremic" and "Jasmina Jeremic
     * Cirovic" are the same woman) reads as two people, which makes a perfectly good account
     * look shared and strips the payment method off someone who can then no longer be paid.
     * Guessing costs more than it catches. 840 is the case that actually occurred, it is
     * institutional by definition, and Beneficiary\Validator already rejects it outright
     * ("Broj racuna pripada budzetu Republike Srbije") so only this migration can introduce
     * one.
     *
     * @return array<string, array{people: int, rows: int}> account => why it was rejected
     */
    private function nonIdentifyingAccounts(): array
    {
        $sql = "SELECT account_number,
                       COUNT(*) AS rows_,
                       COUNT(DISTINCT LOWER(TRIM(name))) AS names,
                       GROUP_CONCAT(DISTINCT name SEPARATOR ' | ') AS variants
                FROM damaged_educator
                WHERE account_number LIKE '840%'
                GROUP BY account_number";

        $rejected = [];
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $account = trim((string) $row['account_number']);
            $rejected[$account] = ['people' => (int) $row['names'], 'rows' => (int) $row['rows_']];
            $this->report['anomalies'][] = sprintf(
                'Account %s is a Republic budget account, not a personal one (%d rows, %d names)'
                    . ' - those rows are grouped by name+school instead, and no payment method is'
                    . ' created for them. Names: %s',
                $account,
                (int) $row['rows_'],
                (int) $row['names'],
                (string) $row['variants'],
            );
        }

        return $rejected;
    }

    /**
     * The "unknown type" row, created on first use. See SchoolType::PLACEHOLDER.
     */
    private function placeholderSchoolType(): SchoolType
    {
        $type = $this->em->getRepository(SchoolType::class)
            ->findOneBy(['name' => SchoolType::PLACEHOLDER]);

        if ($type === null) {
            $type = new SchoolType();
            $type->name = SchoolType::PLACEHOLDER;
            $this->em->persist($type);
            $this->em->flush();
        }

        return $type;
    }
}
