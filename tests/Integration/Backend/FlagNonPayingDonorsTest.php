<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Solidarity\Backend\Action\FlagNonPayingDonors;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;

/**
 * The cron that takes persistent non-payers out of allocation.
 *
 * An unpaid instruction reserves the beneficiary's need for its whole 72h life, so a donor
 * who never pays keeps real money from reaching people, once per round. These tests pin the
 * threshold, the two flags, and — most importantly — the several ways a donor must NOT be
 * flagged: a broken streak, a cleared status, and a mailbox that never received anything.
 */
#[CoversClass(FlagNonPayingDonors::class)]
final class FlagNonPayingDonorsTest extends IntegrationTestCase
{
    // ---- the threshold ---------------------------------------------------------

    public function testADonorIsNotFlaggedOneMissShortOfTheThreshold(): void
    {
        // Two in a row, threshold is three. The boundary is the whole policy, so it is
        // pinned from below as well as above.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 2, expired: 0);

        $this->flag();

        self::assertSame(Donor::STATUS_VERIFIED, $this->reloadDonor($donor)->status);
    }

    public function testThreeUnseenInstructionsInARowFlagTheDonorForContact(): void
    {
        // Never came back, so they are not ignoring anything — the mail is not landing.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 3, expired: 0);

        $this->flag();

        self::assertSame(Donor::STATUS_TRY_TO_CONTACT, $this->reloadDonor($donor)->status);
    }

    public function testThreeSeenButUnpaidInstructionsShadowBanTheDonor(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 0, expired: 3);

        $this->flag();

        self::assertSame(Donor::STATUS_IGNORING_PAYMENTS, $this->reloadDonor($donor)->status);
    }

    // ---- counting each kind separately -----------------------------------------

    public function testNeverSeenInstructionsNeverShadowBanHoweverManyThereAre(): void
    {
        // The case that makes summing the two kinds wrong: seven instructions, not one of
        // them ever seen. That is a delivery problem, and banning them removes the only
        // remedy there is.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 7, expired: 0);

        $this->flag();

        self::assertSame(Donor::STATUS_TRY_TO_CONTACT, $this->reloadDonor($donor)->status);
    }

    public function testAMixedStreakBelowBothThresholdsIsLeftAlone(): void
    {
        // Four misses in a row and still no flag: two ignored and two unseen, neither kind
        // conclusive on its own. This is the room that counting each kind separately buys —
        // against the combined streak this donor would already be flagged.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 2, expired: 2);

        $this->flag();

        self::assertSame(Donor::STATUS_VERIFIED, $this->reloadDonor($donor)->status);
    }

    public function testADonorOverBothThresholdsIsShadowBannedRatherThanContacted(): void
    {
        // Having ignored three, they are demonstrably receiving the mail, so there is
        // nothing to chase — ignoring is checked first for exactly this case.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 3, expired: 3);

        $this->flag();

        self::assertSame(Donor::STATUS_IGNORING_PAYMENTS, $this->reloadDonor($donor)->status);
    }

    // ---- what ends a streak ------------------------------------------------------

    public function testAPaymentInTheMiddleBreaksTheStreak(): void
    {
        // Six misses in total — well past the threshold — but one was honoured after the
        // first five, so only the newest counts. Lifetime totals would flag this donor
        // immediately; a streak must not.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 5, expired: 0, ageHours: 500);
        $this->transactionFor($donor, Transaction::STATUS_CONFIRMED, ageHours: 400);
        $this->transactionFor($donor, Transaction::STATUS_NOT_PAID, ageHours: 300);

        $this->flag();

        self::assertSame(Donor::STATUS_VERIFIED, $this->reloadDonor($donor)->status);
    }

    public function testClearingTheFlagRestartsTheCountRatherThanReFlaggingNextRun(): void
    {
        // The loop this guards against: an admin resets a flagged donor, the old misses are
        // still in the table, and the very next run flags them again within the round.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 6, expired: 0, ageHours: 500);
        $donor->statusChangedAt = new \DateTime('-200 hours');
        $this->em()->flush();

        $this->flag();

        self::assertSame(Donor::STATUS_VERIFIED, $this->reloadDonor($donor)->status);
    }

    public function testAnAlreadyFlaggedDonorIsLeftForAHumanToClear(): void
    {
        // The cron must never escalate its own flag — only a human, or the donor paying.
        $donor = $this->createDonor(status: Donor::STATUS_TRY_TO_CONTACT);
        $this->missesFor($donor, notPaid: 5, expired: 5);

        $this->flag();

        self::assertSame(Donor::STATUS_TRY_TO_CONTACT, $this->reloadDonor($donor)->status);
    }

    // ---- bookkeeping ---------------------------------------------------------------

    public function testFlaggingStampsStatusChangedAtSoTheCountRestarts(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 0, expired: 3);

        $this->flag();

        self::assertNotNull($this->reloadDonor($donor)->statusChangedAt);
    }

    public function testItIsIdempotentAcrossRuns(): void
    {
        // Standalone and self-healing: running it twice must not escalate or re-stamp a
        // donor it already flagged, because the second pass sees them as no longer eligible.
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 0, expired: 3);

        $this->flag();
        $stamped = $this->reloadDonor($donor)->statusChangedAt;
        $this->flag();

        $after = $this->reloadDonor($donor);
        self::assertSame(Donor::STATUS_IGNORING_PAYMENTS, $after->status);
        self::assertEquals($stamped, $after->statusChangedAt);
    }

    // ---- dry run ---------------------------------------------------------------------

    public function testADryRunNamesTheDonorWithoutFlaggingThem(): void
    {
        $donor = $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->missesFor($donor, notPaid: 0, expired: 3);

        $output = $this->flag(dry: true);

        self::assertStringContainsString('DRY-RUN', $output);
        self::assertStringContainsString($donor->email, $output);
        self::assertStringContainsString('1 donor(s) flagged', $output);
        self::assertSame(Donor::STATUS_VERIFIED, $this->reloadDonor($donor)->status);
    }

    public function testANothingToDoRunIsHarmless(): void
    {
        $output = $this->flag();

        self::assertStringContainsString('No donor is over the threshold', $output);
    }

    // ---- helpers ----------------------------------------------------------------------

    /** Misses for $donor, already in their final status. */
    private function missesFor(Donor $donor, int $notPaid, int $expired, int $ageHours = 300): void
    {
        for ($i = 0; $i < $notPaid; $i++) {
            $this->transactionFor($donor, Transaction::STATUS_NOT_PAID, $ageHours + $i);
        }
        for ($i = 0; $i < $expired; $i++) {
            $this->transactionFor($donor, Transaction::STATUS_EXPIRED, $ageHours + $i);
        }
    }

    private function transactionFor(Donor $donor, int $status, int $ageHours): Transaction
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        $transaction = $this->createTransaction($donor, $beneficiary, $project, $period, 5000, $status);
        // createdAt is insertable:false, so it defaults to now and has to be pushed back with
        // raw SQL — the streak is ordered by it.
        $this->backdateTransaction(
            $transaction,
            (new \DateTimeImmutable('-' . $ageHours . ' hours'))->format('Y-m-d H:i:s')
        );

        return $transaction;
    }

    /**
     * clear() + find(), not refresh(): the action writes donor rows through the DBAL
     * connection, so the identity map never learns about the change.
     */
    private function reloadDonor(Donor $donor): Donor
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->find($id);
    }

    /** Runs the action and returns whatever it echoed. */
    private function flag(bool $dry = false): string
    {
        $action = new FlagNonPayingDonors(
            new NullLogger(),
            new Config([]),
            new Engine(),
            $this->em(),
        );

        // CliSkeletor hands the argv tail through as the "params" attribute.
        $request = (new ServerRequest('GET', '/'))->withAttribute('params', $dry ? ['dry'] : ['run']);

        // finally, not a bare pair: if the action throws, an unclosed buffer swallows the
        // failure message and PHPUnit reports every later test in the class as risky
        // ("did not close its own output buffers") instead of showing what actually broke.
        ob_start();
        try {
            $action($request, new Response());
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
