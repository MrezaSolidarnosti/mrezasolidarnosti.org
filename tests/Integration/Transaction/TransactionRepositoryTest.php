<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Repository\TransactionRepository;

#[CoversClass(TransactionRepository::class)]
final class TransactionRepositoryTest extends IntegrationTestCase
{
    private function repo(): TransactionRepository
    {
        return new TransactionRepository($this->em());
    }

    // ---- getRemainingPerPersonLimit -------------------------------------

    public function testRemainingPerPersonLimitIsFullWhenNoDonations(): void
    {
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        self::assertSame(
            Transaction::PER_PERSON_LIMIT,
            $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary),
        );
    }

    public function testRemainingPerPersonLimitSubtractsAllocatedDonations(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 7000, Transaction::STATUS_CONFIRMED);

        self::assertSame(30000 - 12000, $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary));
    }

    public function testRemainingPerPersonLimitFlooredAtZero(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 35000, Transaction::STATUS_PAID);

        self::assertSame(0, $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary));
    }

    public function testRemainingPerPersonLimitIgnoresCancelledTransactions(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 10000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 50000, Transaction::STATUS_CANCELLED);

        self::assertSame(30000 - 10000, $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary));
    }

    public function testRemainingPerPersonLimitCountsAcrossProjects(): void
    {
        $projectA = $this->createProject('MSP');
        $projectB = $this->createProject('MSPR');
        $periodA = $this->createPeriod($projectA);
        $periodB = $this->createPeriod($projectB);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $projectA, $periodA, 10000);
        $this->createTransaction($donor, $beneficiary, $projectB, $periodB, 5000);

        self::assertSame(30000 - 15000, $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary));
    }

    public function testRemainingPerPersonLimitIgnoresPriorYearDonations(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        // A donation from a previous year must not count toward this year's limit.
        $old = $this->createTransaction($donor, $beneficiary, $project, $period, 20000, Transaction::STATUS_PAID);
        $this->backdateTransaction($old, ((int) date('Y') - 1) . '-06-15 12:00:00');

        self::assertSame(
            Transaction::PER_PERSON_LIMIT,
            $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary),
        );
    }

    public function testRemainingPerPersonLimitCountsFromStartOfCurrentYear(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $year = (int) date('Y');
        // Last instant of the previous year — excluded.
        $before = $this->createTransaction($donor, $beneficiary, $project, $period, 8000, Transaction::STATUS_PAID);
        $this->backdateTransaction($before, ($year - 1) . '-12-31 23:59:59');
        // First instant of the current year — included.
        $onBoundary = $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_PAID);
        $this->backdateTransaction($onBoundary, $year . '-01-01 00:00:00');

        self::assertSame(30000 - 5000, $this->repo()->getRemainingPerPersonLimit($donor, $beneficiary));
    }

    // ---- getSumAmountForBeneficiary -------------------------------------

    public function testSumForBeneficiaryIsZeroWhenNoTransactions(): void
    {
        $beneficiary = $this->createBeneficiary();

        self::assertSame(0, $this->repo()->getSumAmountForBeneficiary($beneficiary));
    }

    public function testSumForBeneficiarySumsAllocatedTransactions(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 3000);
        $this->createTransaction($donor, $beneficiary, $project, $period, 4000);

        self::assertSame(7000, $this->repo()->getSumAmountForBeneficiary($beneficiary));
    }

    public function testSumForBeneficiaryFiltersByProject(): void
    {
        $projectA = $this->createProject('MSP');
        $projectB = $this->createProject('MSPR');
        $periodA = $this->createPeriod($projectA);
        $periodB = $this->createPeriod($projectB);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $projectA, $periodA, 3000);
        $this->createTransaction($donor, $beneficiary, $projectB, $periodB, 5000);

        self::assertSame(3000, $this->repo()->getSumAmountForBeneficiary($beneficiary, $projectA));
    }

    public function testSumForBeneficiaryFiltersByPeriod(): void
    {
        $project = $this->createProject();
        $period1 = $this->createPeriod($project, month: 1);
        $period2 = $this->createPeriod($project, month: 2);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period1, 3000);
        $this->createTransaction($donor, $beneficiary, $project, $period2, 5000);

        self::assertSame(3000, $this->repo()->getSumAmountForBeneficiary($beneficiary, $project, $period1));
    }

    public function testSumForBeneficiaryExcludesCancelled(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 3000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 9000, Transaction::STATUS_CANCELLED);

        self::assertSame(3000, $this->repo()->getSumAmountForBeneficiary($beneficiary, $project, $period));
    }

    // ---- getPaidSumAmountForDonorPerProject -----------------------------

    public function testDonorSumIsZeroWhenNoTransactions(): void
    {
        $project = $this->createProject();
        $donor = $this->createDonor();

        self::assertSame(0, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project));
    }

    public function testDonorSumSumsAllocatedForProject(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000);
        $this->createTransaction($donor, $beneficiary, $project, $period, 6000);

        self::assertSame(11000, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project));
    }

    public function testDonorSumFiltersByPaymentType(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_NEW, paymentType: 1);
        $this->createTransaction($donor, $beneficiary, $project, $period, 3000, Transaction::STATUS_NEW, paymentType: 2);

        self::assertSame(5000, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project, 1));
    }

    public function testDonorSumExcludesCancelled(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 9000, Transaction::STATUS_CANCELLED);

        self::assertSame(5000, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project));
    }

    public function testDonorSumMonthlyWindowCountsOnlyLast30Days(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();
        // Donor has a monthly payment method for this project + type 1.
        $this->createDonorPaymentMethod($donor, $project, type: 1, monthly: true);

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_NEW, paymentType: 1);
        $old = $this->createTransaction($donor, $beneficiary, $project, $period, 8000, Transaction::STATUS_NEW, paymentType: 1);
        $this->backdateTransaction($old, '2020-01-01 00:00:00');

        self::assertSame(5000, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project, 1));
    }

    // ---- getTransactionsBySchool (hardcoded to project id 1 / MSP) -------

    public function testGetTransactionsBySchoolReturnsTransactionsForBeneficiariesInThatSchool(): void
    {
        $project = $this->createProjectWithId(1, 'MSP'); // PROJECT_MSP = 1
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $city = $this->createCity();

        $school = $this->createSchool($city, name: 'Target School');
        $beneficiary = $this->createBeneficiary(school: $school);
        $this->createTransaction($donor, $beneficiary, $project, $period, 5000);

        $otherSchool = $this->createSchool($city, name: 'Other School');
        $otherBeneficiary = $this->createBeneficiary(school: $otherSchool);
        $this->createTransaction($donor, $otherBeneficiary, $project, $period, 9000);

        $result = $this->repo()->getTransactionsBySchool($school->getId());

        self::assertCount(1, $result);
        self::assertSame(5000, $result[0]->amount);
    }

    public function testGetTransactionsBySchoolIgnoresOtherProjects(): void
    {
        $mspProject = $this->createProjectWithId(1, 'MSP');
        $otherProject = $this->createProject('MSPR');
        $period = $this->createPeriod($mspProject);
        $otherPeriod = $this->createPeriod($otherProject);
        $donor = $this->createDonor();
        $school = $this->createSchool($this->createCity());
        $beneficiary = $this->createBeneficiary(school: $school);

        // Only the project-1 (MSP) transaction is returned.
        $this->createTransaction($donor, $beneficiary, $otherProject, $otherPeriod, 9000);

        self::assertCount(0, $this->repo()->getTransactionsBySchool($school->getId()));
    }

    public function testDonorSumNonMonthlyCountsAllRegardlessOfDate(): void
    {
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();
        // Non-monthly payment method -> no 30-day window.
        $this->createDonorPaymentMethod($donor, $project, type: 1, monthly: false);

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_NEW, paymentType: 1);
        $old = $this->createTransaction($donor, $beneficiary, $project, $period, 8000, Transaction::STATUS_NEW, paymentType: 1);
        $this->backdateTransaction($old, '2020-01-01 00:00:00');

        self::assertSame(13000, $this->repo()->getPaidSumAmountForDonorPerProject($donor, $project, 1));
    }
}
