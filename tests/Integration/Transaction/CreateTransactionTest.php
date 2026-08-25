<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;

#[CoversClass(TransactionService::class)]
final class CreateTransactionTest extends IntegrationTestCase
{
    private const BANK = 1;
    private const WIRE = 2;

    // ---- scenarios that never reach persistence (skip / early return) ----

    public function testReturnsZeroWhenAmountIsBelowMinimum(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();

        $service = $this->buildService([]);

        // 400 < MIN_TRANSACTION_DONATION_AMOUNT (500)
        self::assertSame(0, $service->allocateAmount($donor, $project, 400, $period, self::BANK));
    }

    public function testSkipsBeneficiaryWithoutMatchingPaymentMethod(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();

        // Beneficiary only accepts wire, but we donate by bank transfer.
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE, accountNumber: null, wireInstructions: 'SWIFT');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 10000);

        $service = $this->buildService([$beneficiary]);

        self::assertSame(0, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(0, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testSkipsBeneficiaryAlreadyAtPerPersonLimit(): void
    {
        $projectA = $this->createProject('MSPR');
        $periodA = $this->createPeriod($projectA, month: 1);
        $projectB = $this->createProject('MSP');
        $periodB = $this->createPeriod($projectB, month: 2);

        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $projectA, $periodA, 10000);

        // 30000 already donated to this beneficiary on another project/period.
        $this->createTransaction($donor, $beneficiary, $projectB, $periodB, 30000, Transaction::STATUS_NEW);

        $service = $this->buildService([$beneficiary]);

        self::assertSame(0, $service->allocateAmount($donor, $projectA, 5000, $periodA, self::BANK));
        self::assertSame(0, $this->allocatedFor($beneficiary, $projectA, $periodA));
    }

    public function testSkipsBeneficiaryNeedingLessThanMinimum(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 300); // remaining 300 <= 500

        $service = $this->buildService([$beneficiary]);

        self::assertSame(0, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(0, $this->allocatedFor($beneficiary, $project, $period));
    }

    // ---- scenarios that reach persistence (probe the factory) ------------

    public function testDonorAmountIsTheBindingConstraint(): void
    {
        [$donor, $project, $period, $beneficiary] = $this->scenario(periodAmount: 10000);

        $service = $this->buildService([$beneficiary]);

        // min(3000 donor, 10000 beneficiary, 30000 per-person) = 3000
        self::assertSame(3000, $service->allocateAmount($donor, $project, 3000, $period, self::BANK));
        self::assertSame(3000, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testBeneficiaryRemainingIsTheBindingConstraint(): void
    {
        [$donor, $project, $period, $beneficiary] = $this->scenario(periodAmount: 4000);

        $service = $this->buildService([$beneficiary]);

        // min(10000 donor, 4000 beneficiary, 30000 per-person) = 4000
        self::assertSame(4000, $service->allocateAmount($donor, $project, 10000, $period, self::BANK));
        self::assertSame(4000, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testPerPersonRemainingIsTheBindingConstraint(): void
    {
        [$donor, $project, $period, $beneficiary] = $this->scenario(periodAmount: 10000);

        // 27000 already donated on another project/period -> per-person remaining 3000
        $otherProject = $this->createProject('MSP');
        $otherPeriod = $this->createPeriod($otherProject, month: 9);
        $this->createTransaction($donor, $beneficiary, $otherProject, $otherPeriod, 27000, Transaction::STATUS_NEW);

        $service = $this->buildService([$beneficiary]);

        // min(10000 donor, 10000 beneficiary, 3000 per-person) = 3000
        self::assertSame(3000, $service->allocateAmount($donor, $project, 10000, $period, self::BANK));
        self::assertSame(3000, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testAllocatesAcrossBeneficiariesUntilDonorExhausted(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK);

        $b1 = $this->beneficiaryWith($project, $period, periodAmount: 5000);
        $b2 = $this->beneficiaryWith($project, $period, periodAmount: 5000);

        $service = $this->buildService([$b1, $b2]);

        // 8000 -> 5000 to b1, 3000 to b2
        self::assertSame(8000, $service->allocateAmount($donor, $project, 8000, $period, self::BANK));
        self::assertSame(5000, $this->allocatedFor($b1, $project, $period));
        self::assertSame(3000, $this->allocatedFor($b2, $project, $period));
    }

    public function testWireTransferAllocationStoresEurAmount(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE, accountNumber: null, wireInstructions: 'SWIFT: TESTRS22');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 20000);

        $service = $this->buildService([$beneficiary]);

        // amount 11750 RSD -> 100 EUR
        self::assertSame(11750, $service->allocateAmount($donor, $project, 11750, $period, self::WIRE));

        $transaction = $this->em()->getRepository(Transaction::class)->findOneBy(['beneficiary' => $beneficiary->getId()]);
        self::assertNotNull($transaction);
        self::assertSame(11750, $transaction->amount);
        self::assertSame(100, $transaction->amountEur);
    }

    public function testLargeDonationRaisesBreakThresholdToTenThousand(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK);

        // Five beneficiaries, each able to absorb the 30k per-person cap.
        $beneficiaries = [];
        for ($i = 0; $i < 5; $i++) {
            $beneficiaries[] = $this->beneficiaryWith($project, $period, periodAmount: 40000);
        }

        $service = $this->buildService($beneficiaries);

        // 128000 > 100000 -> break threshold becomes 10000.
        // b1..b4 each capped at 30000 (per-person) = 120000; leftover 8000 < 10000 -> break before b5.
        self::assertSame(120000, $service->allocateAmount($donor, $project, 128000, $period, self::BANK));
        self::assertSame(30000, $this->allocatedFor($beneficiaries[0], $project, $period));
        self::assertSame(0, $this->allocatedFor($beneficiaries[4], $project, $period));
    }

    // ---- MSP donor-choice (school vs university) filtering ----------------

    public function testMspSchoolDonorSkipsUniversityBeneficiary(): void
    {
        [$donor, $project, $period] = $this->mspContext(Donor::DONATE_TO_SCHOOL);
        $beneficiary = $this->mspBeneficiary($project, $period, schoolTypeId: 9); // university

        $service = $this->buildService([$beneficiary]);

        self::assertSame(0, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(0, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testMspSchoolDonorFundsRegularSchoolBeneficiary(): void
    {
        [$donor, $project, $period] = $this->mspContext(Donor::DONATE_TO_SCHOOL);
        $beneficiary = $this->mspBeneficiary($project, $period, schoolTypeId: 1); // regular school

        $service = $this->buildService([$beneficiary]);

        self::assertSame(5000, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(5000, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testMspUniversityDonorSkipsRegularSchoolBeneficiary(): void
    {
        [$donor, $project, $period] = $this->mspContext(Donor::DONATE_TO_UNI);
        $beneficiary = $this->mspBeneficiary($project, $period, schoolTypeId: 1); // regular school

        $service = $this->buildService([$beneficiary]);

        self::assertSame(0, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(0, $this->allocatedFor($beneficiary, $project, $period));
    }

    public function testMspUniversityDonorFundsUniversityBeneficiary(): void
    {
        [$donor, $project, $period] = $this->mspContext(Donor::DONATE_TO_UNI);
        $beneficiary = $this->mspBeneficiary($project, $period, schoolTypeId: 17); // university

        $service = $this->buildService([$beneficiary]);

        self::assertSame(5000, $service->allocateAmount($donor, $project, 5000, $period, self::BANK));
        self::assertSame(5000, $this->allocatedFor($beneficiary, $project, $period));
    }

    // ---- helpers ---------------------------------------------------------

    /**
     * @return array{0: Donor, 1: \Solidarity\Transaction\Entity\Project, 2: \Solidarity\Period\Entity\Period}
     */
    private function mspContext(int $wantsToDonateTo): array
    {
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor(wantsToDonateTo: $wantsToDonateTo);
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK);

        return [$donor, $project, $period];
    }

    private function mspBeneficiary($project, $period, int $schoolTypeId): Beneficiary
    {
        $school = $this->createSchool($this->createCity(), $this->createSchoolType($schoolTypeId));
        $beneficiary = $this->createBeneficiary(school: $school);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 10000);

        return $beneficiary;
    }

    /**
     * Builds a full donor + project + period + beneficiary ready for a bank
     * transfer allocation, with matching payment methods on both sides.
     *
     * @return array{0: \Solidarity\Donor\Entity\Donor, 1: \Solidarity\Transaction\Entity\Project, 2: \Solidarity\Period\Entity\Period, 3: Beneficiary}
     */
    private function scenario(int $periodAmount): array
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK);
        $beneficiary = $this->beneficiaryWith($project, $period, $periodAmount);

        return [$donor, $project, $period, $beneficiary];
    }

    private function beneficiaryWith($project, $period, int $periodAmount): Beneficiary
    {
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, $periodAmount);

        return $beneficiary;
    }

    private function allocatedFor(Beneficiary $beneficiary, $project, $period): int
    {
        return (new TransactionRepository($this->em()))
            ->getSumAmountForBeneficiary($beneficiary, $project, $period);
    }

    /**
     * Builds the Transaction service with a stubbed BeneficiaryRepository so the test
     * controls which beneficiaries (and in what order) allocation walks for a period —
     * the same control the old action test had by stubbing the beneficiary service.
     *
     * @param Beneficiary[] $beneficiariesForPeriod beneficiaries fetchByPeriod() returns, in order
     */
    private function buildService(array $beneficiariesForPeriod): TransactionService
    {
        $em = $this->em();
        $logger = new NullLogger();

        $validator = new TransactionValidator(
            new CsrfTrueStub(),
            new DonorRepository($em),
            new BeneficiaryRepository($em),
        );

        $beneficiaryRepo = $this->createStub(BeneficiaryRepository::class);
        $beneficiaryRepo->method('fetchByPeriod')->willReturn($beneficiariesForPeriod);

        return new TransactionService(
            new TransactionRepository($em),
            $this->createStub(Session::class),
            $logger,
            new TransactionFilter($validator),
            $this->createStub(ProjectService::class),
            $beneficiaryRepo,
            // Unused by allocateAmount, but the constructor requires it.
            $this->createStub(\Solidarity\Period\Repository\PeriodRepository::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
