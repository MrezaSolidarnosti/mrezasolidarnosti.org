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
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\User\Entity\User;

/**
 * One-off migration of the MSPR project from its own legacy database (solidmspr_old).
 *
 * Run (dry-run, reports only, nothing committed):
 *     php public/cli.php migrateLegacyMspr run
 * Run for real (commits):
 *     php public/cli.php migrateLegacyMspr commit
 *
 * MSPR ran as a **separate instance** of the same Symfony app, so the schema is close to the
 * MSP one but not identical, and this is a separate command rather than a mode on
 * MigrateLegacy — parameterising a file that is already the largest in the codebase would
 * make both harder to read, and both are deleted after go-live.
 *
 * What differs from MSP, and what it forces:
 *
 *  - **No period table at all.** `damaged_educator` has no period_id, so rounds are derived:
 *    one `full` Period per calendar month present in the registrations. See migratePeriods().
 *  - **No schools.** No school/school_type tables and no school_id, so beneficiaries arrive
 *    school-less. Beneficiary::$school is nullable and the app handles it.
 *  - **`city` is a plain string** on the educator rather than an FK. Dropped: the new model
 *    hangs city off School, and inventing schools for MSPR would pollute the school list MSP
 *    delegates work in.
 *  - **`user_donor.is_monthly` is explicit**, where MSP had to infer monthly-vs-one-time from
 *    whether a user_donor row existed at all.
 *  - **No `user_donor.school_type`**, so no wantsToDonateTo — that is an MSP school-vs-
 *    university preference with no MSPR equivalent.
 *
 * And the structural difference that matters most: this runs **on top of a populated
 * database**, not an empty one. MSP is already imported, and the same person can appear in
 * both. So every person is matched on their natural key — email for donors, delegates and
 * admins — and **reused** rather than duplicated. A duplicate donor would split someone's
 * history and break their magic-link login; a duplicate account number would trip
 * Beneficiary\Validator's uniqueness rule and leave both records uneditable.
 *
 * A one-shot command, deleted once the move to production is done, so it is left untested on
 * purpose and excluded from coverage.
 *
 * @codeCoverageIgnore
 */
class MigrateLegacyMspr extends Html
{
    /** Everything here belongs to MSPR, seeded as id 2 by MigrateLegacy::ensureProject(). */
    private const PROJECT_ID = 2;

    private const TYPE_BANK_TRANSFER = 1;
    private const CURRENCY_RSD = 1;

    /**
     * Marks a transaction whose donor no longer exists in the legacy database. Searchable, so
     * "why does this donation have no donor" has an answer years from now.
     */
    private const REDACTED_DONOR_COMMENT = 'Donor deleted in the legacy MSPR system — record kept for accounting';

    private Connection $legacy;

    /** @var array<string, array<int, object>> old id => new managed entity, per type */
    private array $map = ['period' => [], 'donor' => [], 'delegate' => [], 'beneficiary' => []];

    /** old educator id => the Period its registration landed in (so transactions resolve) */
    private array $beneficiaryPeriod = [];

    /** old user id => total legacy transaction amount (one-time donor payment-method amount) */
    private array $transactionSumByDonor = [];

    /** @var array<int, array{0: object, 1: ?string, 2: ?string}> entity, createdAt, updatedAt */
    private array $timestamps = [];

    private array $report = ['counts' => [], 'anomalies' => [], 'notes' => []];

    /** Counters for the reuse-vs-create summary, which is the headline of this migration. */
    private array $reused = ['donor' => 0, 'delegate' => 0, 'admin' => 0];
    private array $created = ['donor' => 0, 'delegate' => 0, 'admin' => 0];

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
        $params = (array) $request->getAttribute('params', []);
        $commit = in_array('commit', $params, true);

        $this->legacy = $this->openLegacyConnection();

        $conn = $this->em->getConnection();
        $conn->setNestTransactionsWithSavepoints(true);
        $conn->beginTransaction();

        try {
            $this->progress(sprintf('=== MSPR MIGRATION %s — started ===', $commit ? 'COMMIT' : 'DRY-RUN'));

            $this->step('checking project 2 exists', fn () => $this->requireProject());
            $this->step('deriving periods from registration dates', fn () => $this->migratePeriods());
            $this->step('users (admins/delegates/donors)', fn () => $this->migrateUsers());
            $this->step('beneficiaries', fn () => $this->migrateBeneficiaries());
            $this->step('transactions', fn () => $this->migrateTransactions());

            $this->step('flush + restore original timestamps', function () {
                $this->em->flush();
                $this->applyTimestamps();
            });

            if ($commit) {
                $conn->commit();
            } else {
                $conn->rollBack();
            }
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->print('MSPR MIGRATION FAILED: ' . $e->getMessage());
            $this->print($e->getTraceAsString());
            return $response;
        }

        $this->printReport($commit);

        return $response;
    }

    // ---- phases ----------------------------------------------------------

    /**
     * MSPR lands in project 2, which MigrateLegacy seeds. Refusing to run without it beats
     * creating it here: the app hardcodes both project ids, and two commands independently
     * deciding what project 2 is would be a good way to end up with MSPR under the MSP code.
     */
    private function requireProject(): void
    {
        $code = $this->em->getConnection()->fetchOne('SELECT code FROM project WHERE id = ?', [self::PROJECT_ID]);

        if ($code === false) {
            throw new \RuntimeException(
                'Project ' . self::PROJECT_ID . ' does not exist. Run migrateLegacy first — it seeds both projects.'
            );
        }
        if ($code !== 'MSPR') {
            throw new \RuntimeException(sprintf(
                'Project %d holds code "%s", expected "MSPR". The app hardcodes these ids; fix before importing.',
                self::PROJECT_ID,
                $code,
            ));
        }
    }

    /**
     * Create one period per calendar month that has registrations.
     *
     * The legacy MSPR app has no period table and no period_id — rounds simply were not
     * modelled — so the only signal is when a registration was made. One `full` period per
     * distinct year+month of `damaged_educator.created_at`, and a registration lands in the
     * month it was created.
     *
     * Transactions then inherit their educator's period rather than being placed by their own
     * date, which is what MSP does (there the period was a column on the educator). It also
     * means a donation made in December against an October registration counts towards the
     * October round, which is the behaviour the rest of the app assumes.
     *
     * Periods are created inactive: these are closed historical rounds, and an active period
     * shows up as an open round in the registration form.
     */
    private function migratePeriods(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $sql = 'SELECT DISTINCT YEAR(created_at) AS y, MONTH(created_at) AS m
                FROM damaged_educator
                WHERE created_at IS NOT NULL
                ORDER BY y, m';

        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $year = (int) $row['y'];
            $month = (int) $row['m'];

            // A re-run against a half-migrated database would otherwise trip the
            // (month, year, type, project_id) unique constraint.
            $existing = $this->em->getRepository(Period::class)->findOneBy([
                'month' => $month, 'year' => $year, 'type' => Period::TYPE_FULL, 'project' => $project,
            ]);
            if ($existing) {
                $this->map['period'][$this->periodKey($year, $month)] = $existing;
                $this->report['notes'][] = sprintf('Period %04d-%02d already existed — reused.', $year, $month);
                continue;
            }

            $period = new Period();
            $period->project = $project;
            $period->month = $month;
            $period->year = $year;
            $period->type = Period::TYPE_FULL;
            $period->active = false;
            $period->processing = false;
            $period->maxAmount = 0;
            // Back-dated to the first of its own month; there is no source row to copy from.
            $this->persist($period, ['created_at' => sprintf('%04d-%02d-01 00:00:00', $year, $month)]);
            $this->map['period'][$this->periodKey($year, $month)] = $period;
        }

        $this->em->flush();
        $this->addCount(sprintf('periods derived: %d', count($this->map['period'])));
    }

    /**
     * Admins, delegates and donors — matched on email against what is already imported.
     *
     * This is the part that differs most from MSP. That migration wrote into an empty
     * database; this one runs on top of it, and the same person can have taken part in both
     * projects. Email is the identity in every case, and an existing record is reused and
     * extended (a delegate gains project 2, a donor gains a payment method for it) rather
     * than duplicated.
     */
    private function migrateUsers(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $this->transactionSumByDonor = $this->legacyTransactionSumByDonor();

        $sql = 'SELECT u.id, u.email, u.roles, u.first_name, u.last_name, u.is_active, u.is_email_verified,
                       u.created_at, u.updated_at, u.last_visit,
                       ud.is_monthly AS donor_is_monthly, ud.amount AS donor_amount,
                       ud.comment AS donor_comment,
                       ud.created_at AS donor_created_at, ud.updated_at AS donor_updated_at
                FROM user u
                LEFT JOIN user_donor ud ON ud.user_id = u.id';

        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $email = trim((string) $row['email']);
            if ($email === '') {
                $this->report['anomalies'][] = sprintf('User #%d has no email — skipped.', $row['id']);
                continue;
            }

            $roles = json_decode((string) $row['roles'], true) ?: [];
            $roles = is_array($roles) ? array_values($roles) : [];

            // Highest role wins, as in MSP: three MSPR users hold both ADMIN and DELEGATE.
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $this->upsertAdmin($row, $email);
                continue;
            }
            if (in_array('ROLE_DELEGATE', $roles, true)) {
                $this->upsertDelegate($row, $email, $project);
                continue;
            }

            $this->upsertDonor($row, $email, $project);
        }

        $this->em->flush();
        $this->addCount(sprintf(
            'admins: %d reused, %d created | delegates: %d reused, %d created | donors: %d reused, %d created',
            $this->reused['admin'], $this->created['admin'],
            $this->reused['delegate'], $this->created['delegate'],
            $this->reused['donor'], $this->created['donor'],
        ));
    }

    /**
     * Beneficiaries: one per person, with a registration in the month they were entered.
     *
     * Same identity rule as MSP — the bank account, falling back to name when absent — but
     * the surrounding data is thinner: no school, no period column, and city dropped. There
     * are only sixteen of them, so anything unexpected is reported rather than resolved by a
     * rule; with this volume a human can look at every line.
     */
    private function migrateBeneficiaries(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $sql = 'SELECT id, name, amount, account_number, status, status_comment, created_by_id, created_at, updated_at
                FROM damaged_educator
                ORDER BY created_at ASC, id ASC';

        /** @var array<string, array{beneficiary: Beneficiary, periods: array<string, RegisteredPeriods>, amounts: array<string, array<int, true>>, comments: string[]}> $groups */
        $groups = [];
        $skippedDuplicates = 0;
        $repeatedRows = 0;

        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $oldId = (int) $row['id'];
            $account = trim((string) ($row['account_number'] ?? ''));
            $key = $account !== '' ? 'acc:' . $account : 'name:' . mb_strtolower(trim((string) $row['name']));

            if (!isset($groups[$key])) {
                // You said MSPR beneficiaries should not overlap with MSP ones. Reported
                // rather than merged if one does: with sixteen rows an unexpected collision
                // is worth a human decision, and silently attaching MSPR registrations to an
                // MSP person would be hard to unpick later.
                if ($account !== '' && $this->accountAlreadyTaken($account)) {
                    $this->report['anomalies'][] = sprintf(
                        'Beneficiary row #%d (%s) carries account %s, which already belongs to an'
                            . ' imported MSP beneficiary — skipped. Decide whether they are the same'
                            . ' person before importing this row.',
                        $oldId,
                        $row['name'],
                        $account,
                    );
                    continue;
                }

                $beneficiary = new Beneficiary();
                $beneficiary->name = (string) $row['name'];
                $beneficiary->status = (int) $row['status'];
                $beneficiary->comment = null;
                $beneficiary->school = null;          // MSPR has no schools
                $beneficiary->createdBy = isset($row['created_by_id'])
                    ? ($this->map['delegate'][(int) $row['created_by_id']] ?? null)
                    : null;
                $this->persist($beneficiary, $row);

                if ($account !== '') {
                    $pm = new BeneficiaryPaymentMethod();
                    $pm->beneficiary = $beneficiary;
                    $pm->type = self::TYPE_BANK_TRANSFER;
                    $pm->accountNumber = $account;
                    $pm->wireInstructions = null;
                    $this->persist($pm, $row);
                }

                $groups[$key] = ['beneficiary' => $beneficiary, 'periods' => [], 'amounts' => [], 'comments' => []];
            }

            $beneficiary = $groups[$key]['beneficiary'];
            if ((int) $row['status'] === Beneficiary::STATUS_NEW) {
                $beneficiary->status = Beneficiary::STATUS_NEW;
            }

            $comment = trim((string) ($row['status_comment'] ?? ''));
            if ($comment !== '' && !in_array($comment, $groups[$key]['comments'], true)) {
                $groups[$key]['comments'][] = $comment;
                $beneficiary->comment = implode(' | ', $groups[$key]['comments']);
            }

            $this->map['beneficiary'][$oldId] = $beneficiary;

            // The period is the month this row was created in — the only signal there is.
            $periodKey = $this->periodKey(
                (int) date('Y', strtotime((string) $row['created_at'])),
                (int) date('n', strtotime((string) $row['created_at'])),
            );
            $period = $this->map['period'][$periodKey] ?? null;
            if (!$period) {
                $this->report['anomalies'][] = sprintf(
                    'Beneficiary row #%d (%s) has no period for %s — skipped registered amount.',
                    $oldId,
                    $row['name'],
                    $periodKey,
                );
                continue;
            }
            $this->beneficiaryPeriod[$oldId] = $period;

            $amount = (int) $row['amount'];

            if ((int) $row['status'] === Beneficiary::STATUS_DELETED && $this->looksLikeDuplicate($comment)) {
                $skippedDuplicates++;
                $this->report['notes'][] = sprintf(
                    'Beneficiary row #%d (%s) struck out as a duplicate ("%s") — no registered amount taken.',
                    $oldId,
                    $row['name'],
                    $comment,
                );
                continue;
            }

            if (isset($groups[$key]['periods'][$periodKey])) {
                // Same rule as MSP: a repeat of an amount already registered for this round
                // is the same request entered twice, not a second one.
                if (isset($groups[$key]['amounts'][$periodKey][$amount])) {
                    $repeatedRows++;
                    $this->report['notes'][] = sprintf(
                        'Beneficiary row #%d (%s) repeats %d for %s — already registered, not added again.',
                        $oldId,
                        $row['name'],
                        $amount,
                        $periodKey,
                    );
                    continue;
                }

                $groups[$key]['periods'][$periodKey]->amount += $amount;
                $groups[$key]['amounts'][$periodKey][$amount] = true;
                $this->report['anomalies'][] = sprintf(
                    'Beneficiary row #%d (%s) adds %d to %s, making %d — a second entry for a'
                        . ' different amount. Check it is a real top-up.',
                    $oldId,
                    $row['name'],
                    $amount,
                    $periodKey,
                    $groups[$key]['periods'][$periodKey]->amount,
                );
                continue;
            }

            $rp = new RegisteredPeriods();
            $rp->beneficiary = $beneficiary;
            $rp->project = $project;
            $rp->period = $period;
            $rp->amount = $amount;
            $this->persist($rp, $row);
            $groups[$key]['periods'][$periodKey] = $rp;
            $groups[$key]['amounts'][$periodKey][$amount] = true;
        }

        $this->em->flush();
        $this->addCount(sprintf(
            'beneficiaries: %d (from %d legacy rows), %d duplicates dropped, %d repeated amounts dropped',
            count($groups),
            count($this->map['beneficiary']),
            $skippedDuplicates,
            $repeatedRows,
        ));
    }

    private function migrateTransactions(): void
    {
        $project = $this->em->getReference(Project::class, self::PROJECT_ID);
        $sql = 'SELECT id, user_id, damaged_educator_id, account_number, amount, status, created_at, updated_at
                FROM transaction';

        $migrated = $skipped = $redactedDonors = 0;
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $donor = isset($row['user_id']) ? ($this->map['donor'][(int) $row['user_id']] ?? null) : null;
            $educatorId = (int) ($row['damaged_educator_id'] ?? 0);
            $beneficiary = $this->map['beneficiary'][$educatorId] ?? null;
            $period = $this->beneficiaryPeriod[$educatorId] ?? null;

            // A missing beneficiary or period cannot be placed at all — the period is derived
            // from the educator row, so losing one loses the other.
            if (!$beneficiary || !$period) {
                $this->report['anomalies'][] = sprintf(
                    'Transaction #%d skipped (beneficiary=%s period=%s).',
                    $row['id'],
                    $beneficiary ? 'ok' : 'MISSING',
                    $period ? 'ok' : 'MISSING',
                );
                $skipped++;
                continue;
            }

            // A missing donor means the person was deleted in the old system, not that the
            // row is broken. Transaction::$donor is nullable for exactly this — "the record
            // is kept for accounting while the personal link is detached" — so the money
            // still counts towards the beneficiary and the round, with nothing identifying
            // who gave it. The legacy transaction carries user_donor_first_name /
            // user_donor_last_name, denormalised copies of the donor's name; those are
            // deliberately not read, since carrying them would reinstate the personal data
            // the deletion removed.
            $redacted = $donor === null;
            if ($redacted) {
                $redactedDonors++;
            }

            $t = new Transaction();
            $t->donor = $donor;
            $t->beneficiary = $beneficiary;
            $t->project = $project;
            $t->period = $period;
            $t->paymentType = self::TYPE_BANK_TRANSFER;
            // The beneficiary's account, not the donor's — it stays either way.
            $t->accountNumber = $row['account_number'] ?? null;
            $t->instructions = null;
            $t->amount = (int) $row['amount'];
            $t->amountEur = 0;
            $t->status = (int) $row['status']; // status constants identical in both apps
            $t->comment = $redacted ? self::REDACTED_DONOR_COMMENT : null;
            $t->paymentCode = null;
            $this->persist($t, $row);
            $migrated++;
        }

        $this->em->flush();
        $this->addCount("transactions: migrated $migrated, skipped $skipped");
        $this->addCount("transactions with a redacted donor (deleted in the old system): $redactedDonors");
    }

    // ---- upserts ---------------------------------------------------------

    private function upsertAdmin(array $row, string $email): void
    {
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $this->reused['admin']++;
            return;
        }

        $user = new User();
        $user->email = $email;
        $user->firstName = (string) $row['first_name'];
        $user->lastName = (string) $row['last_name'];
        $user->displayName = trim($row['first_name'] . ' ' . $row['last_name']);
        $user->role = User::ROLE_ADMIN;
        $user->isActive = (int) ((bool) $row['is_active']);
        $user->ipv4 = null;
        $user->lastLogin = null;
        $this->persist($user, $row);
        $this->created['admin']++;
    }

    /**
     * An existing delegate keeps everything they have and simply gains project 2 — that is
     * what lets one person work across both projects, and it is the overlap you flagged.
     */
    private function upsertDelegate(array $row, string $email, Project $project): void
    {
        $delegate = $this->em->getRepository(Delegate::class)->findOneBy(['email' => $email]);

        if ($delegate) {
            $this->reused['delegate']++;
            $this->report['notes'][] = sprintf('Delegate %s already existed — reused, project MSPR added.', $email);
        } else {
            $delegate = new Delegate();
            $delegate->email = $email;
            $delegate->name = trim($row['first_name'] . ' ' . $row['last_name']);
            $delegate->status = ((bool) $row['is_active']) ? Delegate::STATUS_VERIFIED : Delegate::STATUS_NEW;
            $delegate->phone = '';
            $delegate->verifiedBy = '';
            $delegate->comment = null;
            $delegate->adminComment = null;
            $delegate->ipv4 = null;
            $delegate->lastLogin = null;
            $this->persist($delegate, $row);
            $this->created['delegate']++;
        }

        if (!$delegate->projects->contains($project)) {
            $delegate->projects->add($project);
        }

        $this->map['delegate'][(int) $row['id']] = $delegate;
    }

    /**
     * Donors are matched on email and reused. A duplicate would split someone's giving
     * history across two records and break their magic-link login, which resolves by email.
     *
     * Either way they get a payment method for MSPR: `is_monthly` says which kind, where MSP
     * had to infer it from whether a user_donor row existed at all.
     */
    private function upsertDonor(array $row, string $email, Project $project): void
    {
        $donor = $this->em->getRepository(Donor::class)->findOneBy(['email' => $email]);
        $oldId = (int) $row['id'];

        if ($donor) {
            $this->reused['donor']++;
        } else {
            $donor = new Donor();
            $donor->email = $email;
            $donor->firstName = (string) $row['first_name'];
            $donor->lastName = (string) $row['last_name'];
            $donor->status = ((bool) $row['is_email_verified']) ? Donor::STATUS_VERIFIED : Donor::STATUS_NEW;
            // No MSPR equivalent of MSP's school-vs-university preference.
            $donor->wantsToDonateTo = Donor::DONATE_TO_ALL;
            $donor->comment = $row['donor_comment'] ?? null;
            $donor->isActive = (string) (int) ((bool) $row['is_active']);
            $donor->ipv4 = null;
            $donor->lastLogin = null;
            $donor->lastVisit = !empty($row['last_visit']) ? new \DateTime((string) $row['last_visit']) : null;
            $this->persist($donor, $row);
            $this->created['donor']++;
        }

        $this->map['donor'][$oldId] = $donor;

        // donor_project is a separate join table from the payment methods, and
        // DonorRepository::updateDonationData() maintains both together. `add` rather than
        // assign, because a reused MSP donor keeps project 1 and gains project 2 — and
        // guarded, since a duplicate row would violate the join table's primary key.
        if (!$donor->projects->contains($project)) {
            $donor->projects->add($project);
        }

        // One payment method per donor per project, so a reused MSP donor gains a second one
        // rather than having their MSP method overwritten.
        foreach ($donor->paymentMethods as $existing) {
            if ($existing->project && $existing->project->getId() === $project->getId()) {
                return;
            }
        }

        $isMonthly = (bool) ($row['donor_is_monthly'] ?? false);
        $amount = $isMonthly
            ? (int) ($row['donor_amount'] ?? 0)
            : ($this->transactionSumByDonor[$oldId] ?? 0);

        if ($amount <= 0) {
            return;   // never pledged and never gave — nothing to record
        }

        $pm = new DonorPaymentMethod();
        $pm->donor = $donor;
        $pm->project = $project;
        $pm->type = self::TYPE_BANK_TRANSFER;
        $pm->amount = $amount;
        $pm->monthly = $isMonthly ? 1 : 0;
        $pm->currency = self::CURRENCY_RSD;
        $donor->paymentMethods->add($pm);
        $this->persist($pm, [
            'created_at' => $row['donor_created_at'] ?? $row['created_at'] ?? null,
            'updated_at' => $row['donor_updated_at'] ?? $row['updated_at'] ?? null,
        ]);
    }

    // ---- helpers ---------------------------------------------------------

    private function periodKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /** Whether an account already belongs to a beneficiary imported from MSP. */
    private function accountAlreadyTaken(string $account): bool
    {
        $table = $this->em->getClassMetadata(BeneficiaryPaymentMethod::class)->getTableName();

        return (bool) $this->em->getConnection()->fetchOne(
            sprintf('SELECT 1 FROM `%s` WHERE accountNumber = ? LIMIT 1', $table),
            [$account],
        );
    }

    /** Same broad stem match as MigrateLegacy — see the note there on why it is deliberate. */
    private function looksLikeDuplicate(?string $comment): bool
    {
        $text = mb_strtolower(trim((string) $comment));

        return $text !== '' && (str_contains($text, 'dupl') || str_contains($text, 'дупл'));
    }

    /** @return array<int, int> old user id => total transacted */
    private function legacyTransactionSumByDonor(): array
    {
        $sums = [];
        $sql = 'SELECT user_id, SUM(amount) AS total FROM transaction WHERE user_id IS NOT NULL GROUP BY user_id';
        foreach ($this->legacy->fetchAllAssociative($sql) as $row) {
            $sums[(int) $row['user_id']] = (int) $row['total'];
        }

        return $sums;
    }

    private function persist(object $entity, array $row): void
    {
        $this->em->persist($entity);
        $this->timestamps[] = [$entity, $row['created_at'] ?? null, $row['updated_at'] ?? null];
    }

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

    private function openLegacyConnection(): Connection
    {
        $db = $this->getConfig()->offsetGet('legacyMsprDb');

        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $db->host,
            'dbname' => $db->name,
            'user' => $db->user,
            'password' => $db->pass,
        ]);
    }

    private function addCount(string $line): void
    {
        $this->report['counts'][] = $line;
        $this->progress('  ' . $line);
    }

    private function step(string $label, callable $fn): void
    {
        $this->progress(sprintf('[%s] %s ...', date('H:i:s'), $label));
        $fn();
    }

    private function progress(string $line): void
    {
        echo $line . PHP_EOL;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    private function print(string $line): void
    {
        $this->progress($line);
    }

    private function printReport(bool $commit): void
    {
        $header = '=== MSPR MIGRATION ' . ($commit ? 'COMMITTED' : 'DRY-RUN (rolled back)') . ' ===';

        $lines = [$header];
        foreach ($this->report['counts'] as $line) {
            $lines[] = '  ' . $line;
        }
        $lines[] = '--- anomalies (' . count($this->report['anomalies']) . ') ---';
        foreach ($this->report['anomalies'] as $line) {
            $lines[] = '  ! ' . $line;
        }
        $lines[] = '--- notes (' . count($this->report['notes']) . ') ---';
        foreach ($this->report['notes'] as $line) {
            $lines[] = '  - ' . $line;
        }

        $file = DATA_PATH . '/mspr-migration-report.txt';
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);

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
}
