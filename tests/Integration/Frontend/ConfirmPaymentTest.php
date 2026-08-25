<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Frontend\Action\Donor\ConfirmPayment;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;
use Skeletor\User\Service\Session as UserSession;
use Psr\Log\NullLogger;

/**
 * The endpoint a donor hits to say "I have paid".
 *
 * It is the only place a donor can move a transaction's status, so every guard here is
 * load-bearing: ownership (one donor must not be able to confirm another's instruction),
 * the current status (a cancelled or already-confirmed instruction must not be reopened),
 * and CSRF — which until recently was checked but not enforced.
 */
#[CoversClass(ConfirmPayment::class)]
final class ConfirmPaymentTest extends FrontendActionTestCase
{
    private const WESTERN_UNION = 3;

    public function testADonorCanConfirmTheirOwnInstruction(): void
    {
        [$donor, $transaction] = $this->instruction();

        $response = $this->action($donor)($this->post(['transactionId' => $transaction->getId()]), $this->emptyResponse());

        self::assertTrue($this->decode($response)['success']);
        self::assertSame(Transaction::STATUS_WAITING_CONFIRMATION, $this->reload($transaction)->status);
    }

    // ---- CSRF ---------------------------------------------------------------

    public function testAForgedRequestChangesNothing(): void
    {
        // Regression guard. The check used to set a 401 without returning, so the
        // confirmation below it still ran and only the response said "unauthorised" —
        // a cross-site request could mark someone else's instruction as paid.
        [$donor, $transaction] = $this->instruction();

        $response = $this->action($donor)(
            $this->forgedPost(['transactionId' => $transaction->getId()]),
            $this->emptyResponse(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    public function testAForgedRequestStillHandsBackAFreshToken(): void
    {
        // The form needs a usable token afterwards, otherwise a genuine user whose token
        // merely expired is stuck on a page that can never submit again.
        [$donor, $transaction] = $this->instruction();

        $response = $this->action($donor)(
            $this->forgedPost(['transactionId' => $transaction->getId()]),
            $this->emptyResponse(),
        );

        self::assertNotEmpty($this->decode($response)['data']['token'] ?? '');
    }

    // ---- authentication and ownership ----------------------------------------

    public function testAGuestIsRejected(): void
    {
        [, $transaction] = $this->instruction();

        $response = $this->action(null)($this->post(['transactionId' => $transaction->getId()]), $this->emptyResponse());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    public function testADonorCannotConfirmSomeoneElsesInstruction(): void
    {
        [, $transaction] = $this->instruction();
        $intruder = $this->createDonor();

        $response = $this->action($intruder)(
            $this->post(['transactionId' => $transaction->getId()]),
            $this->emptyResponse(),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    public function testAnUnknownTransactionIsANotFound(): void
    {
        $donor = $this->createDonor();

        $response = $this->action($donor)($this->post(['transactionId' => 999999]), $this->emptyResponse());

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- status guard ----------------------------------------------------------

    public function testAnInstructionThatIsNotOpenCannotBeConfirmed(): void
    {
        // Re-confirming would drag a cancelled or already-reconciled payment back into
        // the queue; only STATUS_NEW is confirmable.
        [$donor, $transaction] = $this->instruction(status: Transaction::STATUS_CANCELLED);

        $response = $this->action($donor)(
            $this->post(['transactionId' => $transaction->getId()]),
            $this->emptyResponse(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_CANCELLED, $this->reload($transaction)->status);
    }

    // ---- Western Union payment code ---------------------------------------------

    public function testWesternUnionRequiresTheMtcnCode(): void
    {
        // Without the MTCN there is no way to reconcile the payment, so the confirmation
        // is refused rather than accepted and left unmatchable.
        [$donor, $transaction] = $this->instruction(paymentType: self::WESTERN_UNION);

        $response = $this->action($donor)(
            $this->post(['transactionId' => $transaction->getId()]),
            $this->emptyResponse(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(Transaction::STATUS_NEW, $this->reload($transaction)->status);
    }

    public function testWesternUnionStoresTheTrimmedPaymentCode(): void
    {
        [$donor, $transaction] = $this->instruction(paymentType: self::WESTERN_UNION);

        $this->action($donor)(
            $this->post(['transactionId' => $transaction->getId(), 'paymentCode' => '  MTCN-123  ']),
            $this->emptyResponse(),
        );

        $reloaded = $this->reload($transaction);
        self::assertSame('MTCN-123', $reloaded->paymentCode);
        self::assertSame(Transaction::STATUS_WAITING_CONFIRMATION, $reloaded->status);
    }

    public function testABankTransferNeedsNoPaymentCode(): void
    {
        [$donor, $transaction] = $this->instruction();

        $this->action($donor)($this->post(['transactionId' => $transaction->getId()]), $this->emptyResponse());

        self::assertSame(Transaction::STATUS_WAITING_CONFIRMATION, $this->reload($transaction)->status);
    }

    // ---- helpers ------------------------------------------------------------------

    /** @return array{0: Donor, 1: Transaction} */
    private function instruction(int $status = Transaction::STATUS_NEW, int $paymentType = 1): array
    {
        $donor = $this->createDonor();
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        return [
            $donor,
            $this->createTransaction($donor, $beneficiary, $project, $period, 5000, $status, paymentType: $paymentType),
        ];
    }

    private function reload(Transaction $transaction): Transaction
    {
        $id = $transaction->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Transaction::class)->find($id);
    }

    private function action(?Donor $donor): ConfirmPayment
    {
        $em = $this->em();

        return new ConfirmPayment(
            $this->logger(),
            $this->config(),
            $this->engine(),
            $this->createStub(DonorService::class),
            $this->navigation(),
            $this->socialLinks(),
            $this->session($donor),
            new TransactionService(
                new TransactionRepository($em),
                $this->createStub(UserSession::class),
                new NullLogger(),
                new TransactionFilter(new TransactionValidator(
                    new CsrfTrueStub(),
                    new DonorRepository($em),
                    new BeneficiaryRepository($em),
                )),
                $this->createStub(ProjectService::class),
                new BeneficiaryRepository($em),
                new PeriodRepository($em),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            new \Solidarity\Tests\Stub\SessionCsrfStub(),
        );
    }
}
