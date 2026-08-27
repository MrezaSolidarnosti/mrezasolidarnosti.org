<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Solidarity\Backend\Action\ExpireInstructions;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;

/**
 * The cron that releases instructions a donor never acted on.
 *
 * NEW counts as allocated, so until the status moves the instruction keeps consuming the
 * donor's pledge, the beneficiary's remaining need and the per-person cap. These tests
 * pin both the window and the NOT_PAID / EXPIRED split, because getting either wrong
 * either strands money or discards a payment the donor actually made.
 */
#[CoversClass(ExpireInstructions::class)]
final class ExpireInstructionsTest extends IntegrationTestCase
{
    // ---- the 72 hour window -----------------------------------------------

    public function testLeavesAnInstructionYoungerThanSeventyTwoHoursAlone(): void
    {
        $transaction = $this->instruction(hoursOld: 71);

        $this->expire();

        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    public function testExpiresAnInstructionOlderThanSeventyTwoHours(): void
    {
        $transaction = $this->instruction(hoursOld: 73);

        $this->expire();

        self::assertNotSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    // ---- which status ------------------------------------------------------

    public function testADonorWhoNeverCameBackGetsNotPaid(): void
    {
        // lastVisit null: the instruction email carries no login, so they never saw it.
        $transaction = $this->instruction(hoursOld: 96, lastVisit: null);

        $this->expire();

        self::assertSame(Transaction::STATUS_NOT_PAID, $this->reload($transaction)->status);
    }

    public function testADonorWhoVisitedAfterwardsGetsExpired(): void
    {
        // Saw it and did not pay — recoverable by hand, so it stays distinguishable.
        $transaction = $this->instruction(hoursOld: 96, lastVisit: '-2 hours');

        $this->expire();

        self::assertSame(Transaction::STATUS_EXPIRED, $this->reload($transaction)->status);
    }

    public function testAVisitBeforeTheInstructionWasCreatedDoesNotCount(): void
    {
        // The visit has to be *after* createdAt; an older one says nothing about this
        // instruction. This is the comparison that makes lastVisit meaningful at all.
        $transaction = $this->instruction(hoursOld: 96, lastVisit: '-120 hours');

        $this->expire();

        self::assertSame(Transaction::STATUS_NOT_PAID, $this->reload($transaction)->status);
    }

    public function testAnInstructionWithNoDonorGetsNotPaid(): void
    {
        // GDPR erasure detaches the donor but keeps the row for accounting.
        $transaction = $this->instruction(hoursOld: 96);
        $transaction->donor = null;
        $this->em()->flush();

        $this->expire();

        self::assertSame(Transaction::STATUS_NOT_PAID, $this->reload($transaction)->status);
    }

    // ---- which rows are eligible -------------------------------------------

    public function testOnlyNewInstructionsAreTouched(): void
    {
        // WAITING_CONFIRMATION means the donor already reported paying. Expiring that
        // would throw away a real payment, which is the worst thing this job could do.
        $waiting = $this->instruction(hoursOld: 96, status: Transaction::STATUS_WAITING_CONFIRMATION);
        $confirmed = $this->instruction(hoursOld: 96, status: Transaction::STATUS_CONFIRMED);
        $cancelled = $this->instruction(hoursOld: 96, status: Transaction::STATUS_CANCELLED);

        $this->expire();

        self::assertSame(Transaction::STATUS_WAITING_CONFIRMATION, $this->reload($waiting)->status);
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->reload($confirmed)->status);
        self::assertSame(Transaction::STATUS_CANCELLED, $this->reload($cancelled)->status);
    }

    // ---- comments -----------------------------------------------------------

    public function testItRecordsWhyTheStatusChanged(): void
    {
        $transaction = $this->instruction(hoursOld: 96);

        $this->expire();

        self::assertStringContainsString('automatski', (string) $this->reload($transaction)->comment);
    }

    public function testAnExistingOperatorNoteIsKeptAndAppendedTo(): void
    {
        $transaction = $this->instruction(hoursOld: 96);
        $transaction->comment = 'Donator zvao telefonom.';
        $this->em()->flush();

        $this->expire();

        $comment = (string) $this->reload($transaction)->comment;
        self::assertStringContainsString('Donator zvao telefonom.', $comment);
        self::assertStringContainsString('automatski', $comment);
    }

    // ---- dry run -------------------------------------------------------------

    public function testDryRunReportsWithoutWriting(): void
    {
        $transaction = $this->instruction(hoursOld: 96);

        $output = $this->expire(dry: true);

        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
        self::assertStringContainsString('DRY-RUN', $output);
        self::assertStringContainsString('1 total', $output);
    }

    public function testTheSummaryCountsBothOutcomes(): void
    {
        $this->instruction(hoursOld: 96, lastVisit: null);        // -> not paid
        $this->instruction(hoursOld: 96, lastVisit: '-1 hours');  // -> expired

        $output = $this->expire();

        self::assertStringContainsString('1 expired', $output);
        self::assertStringContainsString('1 not paid', $output);
        self::assertStringContainsString('2 total', $output);
    }

    public function testANothingToDoRunIsHarmless(): void
    {
        $output = $this->expire();

        self::assertStringContainsString('0 total', $output);
    }

    // ---- helpers --------------------------------------------------------------

    /**
     * One NEW instruction backdated by $hoursOld, from a donor whose lastVisit is
     * $lastVisit (a strtotime-relative string, or null for "never came back").
     */
    private function instruction(
        int $hoursOld,
        ?string $lastVisit = null,
        int $status = Transaction::STATUS_NEW,
    ): Transaction {
        $donor = $this->createDonor();
        if ($lastVisit !== null) {
            $donor->lastVisit = new \DateTime($lastVisit);
            $this->em()->flush();
        }

        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        $transaction = $this->createTransaction($donor, $beneficiary, $project, $period, 5000, $status);
        // createdAt is insertable:false, so it defaults to now and has to be pushed back
        // with raw SQL — the whole job keys off it.
        $this->backdateTransaction($transaction, (new \DateTimeImmutable('-' . $hoursOld . ' hours'))->format('Y-m-d H:i:s'));
        $this->em()->refresh($transaction);

        return $transaction;
    }

    private function reload(Transaction $transaction): Transaction
    {
        // The action calls clear() on the EntityManager, so the old instance is detached.
        return $this->em()->getRepository(Transaction::class)->find($transaction->getId());
    }

    /** Runs the action and returns whatever it echoed. */
    private function expire(bool $dry = false): string
    {
        $action = new ExpireInstructions(
            new NullLogger(),
            new Config([]),
            new Engine(),
            $this->em(),
        );

        // CliSkeletor hands the argv tail through as the "params" attribute.
        $request = (new ServerRequest('GET', '/'))->withAttribute('params', $dry ? ['dry'] : ['run']);

        // finally, not a bare pair: an action that throws would otherwise leave the buffer
        // open, hiding the real failure behind "did not close its own output buffers".
        ob_start();
        try {
            $action($request, new Response());
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
