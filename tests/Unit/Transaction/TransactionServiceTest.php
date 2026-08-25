<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Period\Entity\Period;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;

#[CoversClass(TransactionService::class)]
final class TransactionServiceTest extends TestCase
{
    public function testRejectsTargetStatusThatIsNotConfirmedOrCancelled(): void
    {
        $service = $this->service($this->createStub(TransactionRepository::class));

        $this->expectException(\InvalidArgumentException::class);

        // STATUS_NEW is not a valid target status.
        $service->updateStatus(1, Transaction::STATUS_NEW);
    }

    public function testThrowsWhenTransactionNotFound(): void
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('getById')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction not found.');

        $this->service($repo)->updateStatus(99, Transaction::STATUS_CONFIRMED);
    }

    public function testRejectsTransitionFromLockedStatus(): void
    {
        $transaction = new Transaction();
        $transaction->status = Transaction::STATUS_PAID; // locked

        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('getById')->willReturn($transaction);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/ne moze biti promenjen/');

        $this->service($repo)->updateStatus(5, Transaction::STATUS_CONFIRMED);
    }

    public function testUpdatesStatusForAllowedTransition(): void
    {
        $transaction = new Transaction();
        $transaction->status = Transaction::STATUS_NEW;

        $repo = $this->createMock(TransactionRepository::class);
        $repo->method('getById')->willReturn($transaction);
        $repo->expects($this->once())
            ->method('updateField')
            ->with('status', Transaction::STATUS_CONFIRMED, 5);

        $this->service($repo)->updateStatus(5, Transaction::STATUS_CONFIRMED);
    }

    public function testHasUnmetNeedsIsTrueWhenABeneficiaryStillNeedsMoreThanTheMinimum(): void
    {
        $period = $this->period(5);
        $beneficiary = $this->beneficiaryNeeding($period, 10000);

        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('getSumAmountForBeneficiary')->willReturn(0); // nothing allocated yet

        self::assertTrue($this->service($repo, [$period], [$beneficiary])->hasUnmetNeeds());
    }

    public function testHasUnmetNeedsIsFalseWhenRemainingIsAtOrBelowTheMinimum(): void
    {
        $period = $this->period(5);
        $beneficiary = $this->beneficiaryNeeding($period, 10000);

        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('getSumAmountForBeneficiary')->willReturn(9600); // 400 left, under the 500 floor

        self::assertFalse($this->service($repo, [$period], [$beneficiary])->hasUnmetNeeds());
    }

    public function testHasUnmetNeedsIsFalseWhenNoPeriodsAreProcessing(): void
    {
        $repo = $this->createStub(TransactionRepository::class);

        self::assertFalse($this->service($repo, [], [])->hasUnmetNeeds());
    }

    private function period(int $id): Period
    {
        $period = new Period();
        $period->id = $id;

        return $period;
    }

    private function beneficiaryNeeding(Period $period, int $target): Beneficiary
    {
        $registered = new RegisteredPeriods();
        $registered->period = $period;
        $registered->amount = $target;

        $beneficiary = new Beneficiary();
        $beneficiary->id = 1;
        $beneficiary->registeredPeriods->add($registered);

        return $beneficiary;
    }

    /**
     * @param Period[] $processingPeriods returned by PeriodRepository::fetchProcessing
     * @param Beneficiary[] $beneficiaries returned by BeneficiaryRepository::fetchByPeriod
     */
    private function service(
        TransactionRepository $repo,
        array $processingPeriods = [],
        array $beneficiaries = [],
    ): TransactionService {
        $periodRepo = $this->createStub(PeriodRepository::class);
        $periodRepo->method('fetchProcessing')->willReturn($processingPeriods);

        $beneficiaryRepo = $this->createStub(BeneficiaryRepository::class);
        $beneficiaryRepo->method('fetchByPeriod')->willReturn($beneficiaries);

        return new TransactionService(
            $repo,
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(TransactionFilter::class),
            $this->createStub(ProjectService::class),
            $beneficiaryRepo,
            $periodRepo,
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
