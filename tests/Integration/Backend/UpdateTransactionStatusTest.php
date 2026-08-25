<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Solidarity\Backend\Controller\TransactionController;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

/**
 * Marking payouts paid or cancelled from the transaction list.
 *
 * Two endpoints doing the same job — the row buttons hit updateStatus, the checkbox toolbar
 * hits updateStatusBulk — and they do **not** agree. The bulk one goes through
 * TransactionService::updateStatus(), which refuses to touch a transaction that has already
 * settled (LOCKED_STATUSES). The single one writes with updateField() and enforces nothing.
 * Both are pinned below; which behaviour is the intended one is a separate question.
 *
 * The bulk endpoint's reporting is also order-dependent in a way that is easy to miss, so
 * the two orderings are asserted separately rather than assumed symmetric.
 */
#[CoversClass(TransactionController::class)]
final class UpdateTransactionStatusTest extends TransactionControllerTestCase
{
    private ?Project $project = null;
    private ?Period $period = null;
    private ?Donor $donor = null;

    // ---- one row at a time ------------------------------------------------------

    public function testConfirmingATransactionWritesTheStatusAndSaysSo(): void
    {
        $transaction = $this->newTransaction();

        $response = $this->updateStatus($transaction, Transaction::STATUS_CONFIRMED);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decode($response)['success']);
        self::assertSame('Transakcija je potvrđena.', $this->decode($response)['message']);
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($transaction));
    }

    public function testCancellingATransactionWritesTheStatusAndSaysSo(): void
    {
        $transaction = $this->newTransaction();

        $response = $this->updateStatus($transaction, Transaction::STATUS_CANCELLED);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Transakcija je otkazana.', $this->decode($response)['message']);
        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    #[DataProvider('statusesTheDashboardMustRefuse')]
    public function testOnlyConfirmedAndCancelledCanBeSetFromTheDashboard(int $status): void
    {
        // The list offers two buttons; anything else arriving means a hand-built request or
        // a stale front end. Rejecting before the write is what keeps a transaction from
        // being pushed into a state the UI has no way back out of.
        $transaction = $this->newTransaction();

        $response = $this->updateStatus($transaction, $status);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame('Status transakcije nije validan.', $this->decode($response)['message']);
        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
    }

    /** @return array<string, array{int}> */
    public static function statusesTheDashboardMustRefuse(): array
    {
        return [
            'nothing at all' => [0],
            'new' => [Transaction::STATUS_NEW],
            'waiting confirmation' => [Transaction::STATUS_WAITING_CONFIRMATION],
            'not paid' => [Transaction::STATUS_NOT_PAID],
            'expired' => [Transaction::STATUS_EXPIRED],
            // Set by the delegate payout round, not by hand.
            'paid' => [Transaction::STATUS_PAID],
            'off the end of the enum' => [99],
        ];
    }

    public function testTheSingleEndpointRewritesATransactionThatHasAlreadySettled(): void
    {
        // updateField() straight to a DQL UPDATE — LOCKED_STATUSES never gets a look in.
        // The bulk endpoint refuses the same request; see the pair of tests below.
        $transaction = $this->newTransaction(Transaction::STATUS_PAID);

        $response = $this->updateStatus($transaction, Transaction::STATUS_CANCELLED);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($transaction));
    }

    // ---- several rows at once ----------------------------------------------------

    public function testItConfirmsEveryIdItIsGiven(): void
    {
        $first = $this->newTransaction();
        $second = $this->newTransaction();

        $response = $this->updateStatusBulk([$first, $second], Transaction::STATUS_CONFIRMED);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decode($response)['success']);
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($first));
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($second));
    }

    public function testItCancelsEveryIdItIsGiven(): void
    {
        $first = $this->newTransaction();
        $second = $this->newTransaction();

        $this->updateStatusBulk([$first, $second], Transaction::STATUS_CANCELLED);

        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($first));
        self::assertSame(Transaction::STATUS_CANCELLED, $this->statusOf($second));
    }

    public function testAnInvalidStatusIsRejectedBeforeAnythingIsWritten(): void
    {
        $transaction = $this->newTransaction();

        $response = $this->updateStatusBulk([$transaction], Transaction::STATUS_PAID);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
    }

    public function testASelectionWithNoIdsIsReportedAsAFailure(): void
    {
        // The toolbar can be clicked with nothing ticked. Note the status code: the outer
        // catch reports success=false but leaves $returnStatus at 200, so a caller checking
        // only the HTTP status sees this as fine.
        $transaction = $this->newTransaction();

        $response = $this->updateStatusBulk([], Transaction::STATUS_CONFIRMED);

        self::assertFalse($this->decode($response)['success']);
        self::assertSame('No ids provided.', $this->decode($response)['message']);
        self::assertSame(Transaction::STATUS_NEW, $this->statusOf($transaction));
    }

    public function testTheBulkEndpointRefusesATransactionThatHasAlreadySettled(): void
    {
        // The other half of the divergence: identical request, opposite outcome.
        $transaction = $this->newTransaction(Transaction::STATUS_PAID);

        $response = $this->updateStatusBulk([$transaction], Transaction::STATUS_CANCELLED);

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame(Transaction::STATUS_PAID, $this->statusOf($transaction));
    }

    // ---- partial failure ----------------------------------------------------------

    public function testARefusedRowDoesNotStopTheOthersFromBeingWritten(): void
    {
        $settled = $this->newTransaction(Transaction::STATUS_PAID);
        $fresh = $this->newTransaction();

        $this->updateStatusBulk([$settled, $fresh], Transaction::STATUS_CONFIRMED);

        self::assertSame(Transaction::STATUS_PAID, $this->statusOf($settled));
        self::assertSame(Transaction::STATUS_CONFIRMED, $this->statusOf($fresh));
    }

    public function testAPartialFailureReportsWhicheverRowWasProcessedLast(): void
    {
        // $success and $message are reassigned on every iteration, so they describe the last
        // row rather than the batch — while $returnStatus is sticky once something fails.
        // With the failure first, the caller gets HTTP 500 alongside success=true and a
        // cheerful message; reverse the order and the same batch reports failure. Neither
        // response mentions *which* rows were refused: $failed is collected and discarded.
        $settled = $this->newTransaction(Transaction::STATUS_PAID);
        $fresh = $this->newTransaction();

        $failureFirst = $this->decode($this->updateStatusBulk([$settled, $fresh], Transaction::STATUS_CONFIRMED));
        self::assertTrue($failureFirst['success']);
        self::assertSame('Transakcija su potvrđene.', $failureFirst['message']);

        $anotherSettled = $this->newTransaction(Transaction::STATUS_PAID);
        $anotherFresh = $this->newTransaction();

        $failureLast = $this->decode($this->updateStatusBulk([$anotherFresh, $anotherSettled], Transaction::STATUS_CONFIRMED));
        self::assertFalse($failureLast['success']);
        self::assertStringContainsString('ne moze biti promenjen', $failureLast['message']);
    }

    public function testAnIdThatIsNotInTheDatabaseIsReportedRatherThanIgnored(): void
    {
        $response = $this->updateStatusBulkIds([999999], Transaction::STATUS_CONFIRMED);

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame('Transaction not found.', $this->decode($response)['message']);
    }

    // ---- helpers -------------------------------------------------------------------

    private function newTransaction(int $status = Transaction::STATUS_NEW): Transaction
    {
        $this->project ??= $this->createProject();
        $this->period ??= $this->createPeriod($this->project);
        $this->donor ??= $this->createDonor();

        return $this->createTransaction(
            $this->donor,
            $this->createBeneficiary('Beneficiary ' . uniqid()),
            $this->project,
            $this->period,
            5000,
            $status,
        );
    }

    private function updateStatus(Transaction $transaction, int $status): ResponseInterface
    {
        $controller = $this->controller();
        $controller->setRequest(
            $this->get('/transaction/updateStatus/', ['status' => (string) $status])
                ->withAttribute('id', (string) $transaction->getId()),
        );

        return $controller->updateStatus();
    }

    /** @param Transaction[] $transactions */
    private function updateStatusBulk(array $transactions, int $status): ResponseInterface
    {
        return $this->updateStatusBulkIds(
            array_map(static fn (Transaction $t): int => (int) $t->getId(), $transactions),
            $status,
        );
    }

    /** @param int[] $ids an empty list omits the key entirely, as the front end does */
    private function updateStatusBulkIds(array $ids, int $status): ResponseInterface
    {
        $controller = $this->controller();
        $controller->setRequest($this->postJson(
            '/transaction/updateStatusBulk/',
            $ids === [] ? [] : ['ids' => $ids],
            ['status' => (string) $status],
        ));

        return $controller->updateStatusBulk();
    }

    /**
     * Both endpoints write through DQL, which never reaches the identity map — the entity in
     * memory keeps its old status. Read the column back instead of re-fetching.
     */
    private function statusOf(Transaction $transaction): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            'SELECT status FROM `transaction` WHERE id = ?',
            [$transaction->getId()],
        );
    }
}
