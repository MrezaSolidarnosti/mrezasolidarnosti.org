<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\Core\Config\Config;
use Solidarity\Backend\Action\CreateTransactionUrgent;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Project as ProjectEntity;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * Donor selection for the urgent round.
 *
 * The whole action is one overridden method, so this is the whole action. Everything after
 * selection is CreateTransaction's and is covered by its own tests.
 *
 * Worth pinning rather than eyeballing: the ordering key is a GREATEST() over two dates from
 * different tables with a status filter on one of them, and getting any part of it wrong
 * fails silently — the round still runs, still allocates, still mails, and just quietly picks
 * the wrong people. There is no error to notice.
 */
#[CoversClass(CreateTransactionUrgent::class)]
final class CreateTransactionUrgentSelectionTest extends IntegrationTestCase
{
    public function testDonorsComeBackMostRecentlyActiveFirst(): void
    {
        $project = $this->createProject();

        // Every date here is relative to now, deliberately. Fixed literals would decide by
        // calendar whether a donation lands inside the 30-day monthly window, so the suite
        // would start failing on a date nobody chose.

        // Registered long ago, donated recently — active. -40 days keeps the donation clear
        // of the 30-day window, so it drives the ordering without draining the pledge.
        $recentDonor = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($recentDonor, $this->daysAgo(800));
        $this->honouredDonation($recentDonor, $project, $this->daysAgo(40));

        // Registered recently, never donated — also active, just younger evidence.
        $newSignup = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($newSignup, $this->daysAgo(60));

        // Registered long ago, donated long ago — dormant.
        $dormant = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($dormant, $this->daysAgo(800));
        $this->honouredDonation($dormant, $project, $this->daysAgo(400));

        $selected = $this->select([$project], ['run']);

        self::assertSame(
            [$recentDonor->getId(), $newSignup->getId(), $dormant->getId()],
            array_keys($selected),
            'Expected order: recent donation, then recent registration, then the dormant donor.'
        );
    }

    /**
     * A donor whose most recent transaction is one they did NOT pay must not rank on it.
     *
     * This is the trap the ordering exists to avoid. The scheduled round hands instructions to
     * dormant donors by design, so the freshest row against a dormant donor is very often an
     * EXPIRED one it just created. Ranking on "last transaction" instead of "last honoured
     * transaction" would therefore promote exactly the donors this action is meant to skip,
     * and it would look like it was working.
     */
    public function testAnUnpaidInstructionIsNotEvidenceOfActivity(): void
    {
        $project = $this->createProject();

        $paid = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($paid, $this->daysAgo(800));
        $this->honouredDonation($paid, $project, $this->daysAgo(60));

        // Newer than the other donor's payment, and worth nothing: an EXPIRED instruction is
        // what the scheduled round leaves behind on a donor who ignored it.
        $expiredOnly = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($expiredOnly, $this->daysAgo(800));
        $this->honouredDonation($expiredOnly, $project, $this->daysAgo(5), Transaction::STATUS_EXPIRED);

        $selected = $this->select([$project], ['run']);

        self::assertSame(
            [$paid->getId(), $expiredOnly->getId()],
            array_keys($selected),
            'The donor with the newer EXPIRED instruction must rank below the one who actually paid.'
        );
    }

    public function testTargetStopsSelectionOnceCoveredAndKeepsTheDonorThatCrossedIt(): void
    {
        $project = $this->createProject();

        $first = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($first, $this->daysAgo(1));
        $second = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($second, $this->daysAgo(2));
        $third = $this->pledgingDonor($project, 10000);
        $this->backdateDonor($third, $this->daysAgo(3));

        // 15,000 is covered part-way through the second donor, who is taken whole — a pledge
        // cannot be selected in halves.
        $selected = $this->select([$project], ['run', 'target=15000']);

        self::assertSame([$first->getId(), $second->getId()], array_keys($selected));
    }

    public function testNoTargetTakesTheWholeEligiblePool(): void
    {
        $project = $this->createProject();

        $a = $this->pledgingDonor($project, 10000);
        $b = $this->pledgingDonor($project, 10000);

        $selected = $this->select([$project], ['run']);

        self::assertCount(2, $selected);
        self::assertEqualsCanonicalizing([$a->getId(), $b->getId()], array_keys($selected));
    }

    /**
     * The flagged and the switched-off stay out, exactly as in the scheduled round.
     *
     * Urgency is not a reason to reopen donors FlagNonPayingDonors has already taken out of
     * allocation: they are the least likely to convert, and handing them another instruction
     * is how a donor who asked to be left alone gets chased again.
     */
    public function testFlaggedAndInactiveDonorsAreExcluded(): void
    {
        $project = $this->createProject();

        $eligible = $this->pledgingDonor($project, 10000);

        $flagged = $this->pledgingDonor($project, 10000, Donor::STATUS_TRY_TO_CONTACT);
        $ignoring = $this->pledgingDonor($project, 10000, Donor::STATUS_IGNORING_PAYMENTS);
        $deleted = $this->pledgingDonor($project, 10000, Donor::STATUS_DELETED);

        $switchedOff = $this->pledgingDonor($project, 10000);
        $switchedOff->isActive = '0';
        $this->em()->flush();

        $selected = $this->select([$project], ['run']);

        self::assertSame([$eligible->getId()], array_keys($selected));
    }

    /**
     * A pledge the allocator will not touch must not pull its donor into the round.
     *
     * createBalancedForDonor() skips anything that is neither monthly nor allocateUntilSpent,
     * so selecting on it would count money toward the target that the round then cannot move
     * — the run would report a full selection and allocate nothing.
     */
    public function testAPledgeTheCronIgnoresDoesNotMakeADonorSelectable(): void
    {
        $project = $this->createProject();

        $selectable = $this->pledgingDonor($project, 10000);

        $inertPledge = $this->createDonor();
        $this->linkDonorToProject($inertPledge, $project);
        $this->createDonorPaymentMethod(
            $inertPledge, $project, type: 1, monthly: false, amount: 10000, allocateUntilSpent: false
        );

        $selected = $this->select([$project], ['run']);

        self::assertSame([$selectable->getId()], array_keys($selected));
    }

    /**
     * Monthly pledges are measured over the last 30 days, so an allocation from last month
     * has already replenished and the donor is selectable again at full value.
     */
    public function testAMonthlyPledgeIgnoresSpendOlderThanThirtyDays(): void
    {
        $project = $this->createProject();

        $drained = $this->pledgingDonor($project, 10000);
        $this->allocation($drained, $project, 10000, $this->daysAgo(3));

        $replenished = $this->pledgingDonor($project, 10000);
        $this->allocation($replenished, $project, 10000, $this->daysAgo(45));

        $selected = $this->select([$project], ['run']);

        self::assertSame(
            [$replenished->getId()],
            array_keys($selected),
            'The donor allocated 3 days ago has nothing left this window; the one from 45 days ago does.'
        );
    }

    // -----------------------------------------------------------------------------------

    /** Relative, never literal: see the note in testDonorsComeBackMostRecentlyActiveFirst(). */
    private function daysAgo(int $days): string
    {
        return (new \DateTimeImmutable(sprintf('-%d days', $days)))->format('Y-m-d H:i:s');
    }

    /** A donor linked to the project with one monthly pledge — the shape the cron will act on. */
    private function pledgingDonor(
        ProjectEntity $project,
        int $amount,
        int $status = Donor::STATUS_VERIFIED,
    ): Donor {
        $donor = $this->createDonor(status: $status);
        $this->linkDonorToProject($donor, $project);
        $this->createDonorPaymentMethod($donor, $project, type: 1, monthly: true, amount: $amount);

        return $donor;
    }

    /** A transaction in an honoured status, backdated so it can drive the ordering. */
    private function honouredDonation(
        Donor $donor,
        ProjectEntity $project,
        string $at,
        int $status = Transaction::STATUS_PAID,
    ): void {
        $this->allocation($donor, $project, 1, $at, $status);
    }

    private function allocation(
        Donor $donor,
        ProjectEntity $project,
        int $amount,
        string $at,
        int $status = Transaction::STATUS_PAID,
    ): void {
        $beneficiary = $this->createBeneficiary();
        $transaction = $this->createTransaction($donor, $beneficiary, $project, $this->period($project), $amount, $status);
        $this->backdateTransaction($transaction, $at);
    }

    /**
     * One period per project, reused.
     *
     * createPeriod()'s defaults are the same month/year/type every call, and period has a
     * unique constraint on (month, year, type, project_id) — so calling it twice for one
     * project is a duplicate key, which inside a flush closes the EntityManager and fails
     * every remaining test in the process instead of just this one.
     *
     * @var array<int, \Solidarity\Period\Entity\Period>
     */
    private array $periods = [];

    private function period(ProjectEntity $project): \Solidarity\Period\Entity\Period
    {
        return $this->periods[$project->id] ??= $this->createPeriod($project);
    }

    /**
     * selectDonors() is protected, and deliberately so — it is an override point, not API.
     * Reflection here rather than a public wrapper on the action, which would exist only for
     * the test and invite something else to call it.
     *
     * @param ProjectEntity[] $projects
     * @return array<int, Donor>
     */
    private function select(array $projects, array $params): array
    {
        $action = new CreateTransactionUrgent(
            new NullLogger(),
            new Config([]),
            new Engine(),
            $this->createStub(TransactionService::class),
            $this->createStub(ProjectService::class),
            $this->createStub(DonorService::class),
            $this->createStub(Mailer::class),
            $this->em(),
        );

        $method = new \ReflectionMethod($action, 'selectDonors');

        // selectDonors() prints its selection summary — that is a feature of the round, but
        // here it would just interleave with PHPUnit's own output.
        ob_start();
        try {
            return $method->invoke($action, $projects, $params);
        } finally {
            ob_end_clean();
        }
    }
}
