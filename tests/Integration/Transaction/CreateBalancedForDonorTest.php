<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Period\Entity\Period;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;

/**
 * Covers createBalancedForDonor() — the method the createTransactions cron actually calls.
 *
 * CreateTransactionTest covers allocateAmount(), which shares allocateToBeneficiary() but
 * is reached by no production code path. Everything upstream of that shared gate is only
 * exercised here: building per-payment-type budgets from the donor's pledges (pledge minus
 * prior spend), the per-project minSlice, collecting candidates across a project's
 * processing periods, and the round-robin across projects.
 *
 * Unlike CreateTransactionTest this uses a REAL BeneficiaryRepository rather than stubbing
 * fetchByPeriod(), because which beneficiaries a period yields — and in what order — is
 * part of what is under test.
 */
#[CoversClass(TransactionService::class)]
final class CreateBalancedForDonorTest extends IntegrationTestCase
{
    private const BANK = 1;
    private const WIRE = 2;

    // ---- the happy paths -------------------------------------------------

    public function testAllocatesThePledgeToAMatchingBeneficiary(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 5000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(5000, $this->service()->createBalancedForDonor($donor, [$project]));

        $created = $this->newTransactions($donor);
        self::assertCount(1, $created);
        self::assertSame(5000, $created[0]->amount);
        self::assertSame(self::BANK, $created[0]->paymentType);
        self::assertSame($beneficiary->getId(), $created[0]->beneficiary->getId());
        // Bank transfers carry the account number, never wire instructions.
        self::assertNotNull($created[0]->accountNumber);
        self::assertNull($created[0]->instructions);
    }

    public function testEurPledgeIsConvertedToRsdAndStoresTheEurAmount(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        // 100 EUR at the fixed 117.5 rate = 11750 RSD of budget.
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE, monthly: true, amount: 100, currency: 2);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE, accountNumber: null, wireInstructions: 'SWIFT TESTRS22');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(11750, $this->service()->createBalancedForDonor($donor, [$project]));

        $created = $this->newTransactions($donor);
        self::assertCount(1, $created);
        self::assertSame(11750, $created[0]->amount);
        self::assertSame(100, $created[0]->amountEur);
        self::assertNull($created[0]->accountNumber);
        self::assertSame('SWIFT TESTRS22', $created[0]->instructions);
    }

    public function testSpreadsOnePledgeAcrossBeneficiariesWhenThePerPersonCapBites(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 50000);

        foreach (['A', 'B'] as $name) {
            $beneficiary = $this->createBeneficiary($name);
            $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
            $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);
        }

        // 30000 to the first (the per-person yearly cap), the remaining 20000 to the second.
        self::assertSame(50000, $this->service()->createBalancedForDonor($donor, [$project]));

        $created = $this->newTransactions($donor);
        self::assertCount(2, $created);
        self::assertSame(30000, $created[0]->amount);
        self::assertSame(20000, $created[1]->amount);
        self::assertNotSame($created[0]->beneficiary->getId(), $created[1]->beneficiary->getId());
    }

    public function testGivesEachProjectItsOwnPledge(): void
    {
        $donor = $this->createDonor();
        $projects = [];

        foreach (['MSPR', 'MSO'] as $code) {
            $project = $this->createProject($code);
            $period = $this->processingPeriod($project);
            $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 10000);

            $beneficiary = $this->createBeneficiary($code . ' beneficiary');
            $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
            $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

            $projects[] = $project;
        }

        // Money never moves between projects: each keeps its own 10000.
        self::assertSame(20000, $this->service()->createBalancedForDonor($donor, $projects));

        $created = $this->newTransactions($donor);
        self::assertCount(2, $created);
        self::assertSame([10000, 10000], [$created[0]->amount, $created[1]->amount]);
        self::assertNotSame($created[0]->project->getId(), $created[1]->project->getId());
    }

    // ---- budget construction: pledge minus prior spend --------------------

    public function testMonthlyPledgeIgnoresSpendOlderThanThirtyDays(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 10000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        // The whole pledge was spent, but long enough ago to fall outside the window.
        $old = $this->createTransaction($donor, $beneficiary, $project, $period, 10000, Transaction::STATUS_CONFIRMED);
        $this->backdateTransaction($old, '2020-01-01 00:00:00');

        self::assertSame(10000, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testAPlainNonMonthlyPledgeIsNeverAllocatedByTheCron(): void
    {
        // Neither flag set, so it is not the cron's business: one-time giving is initiated by
        // the donor from their profile and goes through createForDonor() with the amounts they
        // chose at that moment. The cron used to read every payment method a donor had, so an
        // admin who left the "monthly" box unchecked on the donor form created a row it then
        // treated as a standing pledge and generated instructions from.
        //
        // Note there is no prior spend at all — the pledge is untouched and would be fully
        // allocatable if the gate were missing.
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: false, amount: 10000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
        self::assertCount(0, $this->newTransactions($donor));
    }

    public function testALegacyLumpSumIsAllocatedWhileItStillHasMoneyLeft(): void
    {
        // Migrated from the old app, where a one-time pledge was spread across instructions
        // automatically. Still owed, so it is flagged to drain even though it is not monthly.
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod(
            $donor, $project, type: self::BANK, monthly: false, amount: 10000, allocateUntilSpent: true
        );

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(10000, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testALegacyLumpSumStopsOnceItIsSpent(): void
    {
        // The reason this needs no expiry date and no cleanup: spend is counted over all time
        // for a non-monthly pledge, so once it is used up the remainder never reaches the
        // floor again and the row is inert forever. A monthly pledge would have replenished
        // here — which is exactly why `monthly` could not be reused as the flag.
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod(
            $donor, $project, type: self::BANK, monthly: false, amount: 10000, allocateUntilSpent: true
        );

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        // Spent long ago: a monthly pledge would ignore this as outside the 30-day window.
        $old = $this->createTransaction($donor, $beneficiary, $project, $period, 10000, Transaction::STATUS_CONFIRMED);
        $this->backdateTransaction($old, '2020-01-01 00:00:00');

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
        self::assertCount(0, $this->newTransactions($donor));
    }

    public function testOnlyTheMonthlyHalfOfAMixedPledgeIsAllocated(): void
    {
        // A donor can hold both: a standing monthly pledge and a leftover one-time method.
        // Only the monthly one is the cron's business, so the budget is 5000, not 55000.
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 5000);
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE, monthly: false, amount: 50000);
        // ...and the one-time one is not flagged to drain, so it stays out.

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(5000, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testAllocatesNothingWhenTheDonorHasNoPledgeForTheProject(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    // ---- minSlice ---------------------------------------------------------

    public function testDropsABudgetBelowTheRaisedMinSliceOnLargePledges(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        // 120000 + (68 EUR = 7990) = 127990, over the 100000 line, so minSlice becomes 10000
        // and the 7990 wire budget is no longer usable.
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 120000);
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE, monthly: true, amount: 68, currency: 2);

        // Only accepts wire — the one budget that just got dropped.
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE, accountNumber: null, wireInstructions: 'SWIFT');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testKeepsSmallBudgetsWhenTotalPledgesStayUnderTheThreshold(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        // Same shape as above but under 100000, so minSlice stays at 500 and wire survives.
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 50000);
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE, monthly: true, amount: 68, currency: 2);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::WIRE, accountNumber: null, wireInstructions: 'SWIFT');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(7990, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    // ---- candidate collection --------------------------------------------

    public function testIgnoresPeriodsThatAreNotProcessing(): void
    {
        $project = $this->createProject('MSPR');
        // Deliberately not switched to processing.
        $period = $this->createPeriod($project);
        $project->periods->add($period);

        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 5000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testIgnoresBeneficiariesThatAreNotStatusNew(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 5000);

        $beneficiary = $this->createBeneficiary('Problematic', status: Beneficiary::STATUS_PROBLEM);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testIgnoresBeneficiariesWithNoPaymentMethodAtAll(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 5000);

        $beneficiary = $this->createBeneficiary('No payment method');
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testAllocatesNothingWhenNoBeneficiaryAcceptsThePledgedType(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::WIRE, monthly: true, amount: 100, currency: 2);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    // ---- caps -------------------------------------------------------------

    public function testAllocatesOnlyTheHeadroomLeftUnderThePerPersonCap(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 50000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        // 28000 already given this year leaves exactly 2000 of the 30000 cap.
        $this->createTransaction($donor, $beneficiary, $project, $period, 28000, Transaction::STATUS_CONFIRMED);

        self::assertSame(2000, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testAllocatesNothingWhenThePerPersonCapIsAlreadyReached(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 50000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        $this->createTransaction($donor, $beneficiary, $project, $period, 30000, Transaction::STATUS_CONFIRMED);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testAllocatesNothingWhenTheHeadroomIsBelowTheMinimum(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 50000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        // 29800 given leaves 200 of the per-person cap. Every individual constraint clears
        // its own floor — budget 50000, need 210200, cap 200 > 0 — but their minimum does
        // not, and a 200 RSD bank transfer is not worth asking anyone to make.
        $this->createTransaction($donor, $beneficiary, $project, $period, 29800, Transaction::STATUS_CONFIRMED);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
        self::assertCount(0, $this->newTransactions($donor));
    }

    public function testAllocatesNothingWhenTheRemainingNeedIsBelowTheMinimum(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 10000);

        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 20000);

        // A different donor already covered all but 200, which is under the 500 floor.
        $other = $this->createDonor();
        $this->createTransaction($other, $beneficiary, $project, $period, 19800, Transaction::STATUS_CONFIRMED);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    // ---- MSP-only school/uni preference -----------------------------------

    public function testUniversityDonorSkipsARegularSchoolBeneficiaryOnMsp(): void
    {
        [$project, $period] = $this->mspProject();
        $donor = $this->createDonor(wantsToDonateTo: Donor::DONATE_TO_UNI);
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 10000);

        $this->beneficiaryAtSchoolOfType($project, $period, typeId: 5);

        self::assertSame(0, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    public function testUniversityDonorFundsAUniversityBeneficiaryOnMsp(): void
    {
        [$project, $period] = $this->mspProject();
        $donor = $this->createDonor(wantsToDonateTo: Donor::DONATE_TO_UNI);
        $this->createDonorPaymentMethod($donor, $project, type: self::BANK, monthly: true, amount: 10000);

        // 9 and 17 are the university school types the preference gate hardcodes.
        $this->beneficiaryAtSchoolOfType($project, $period, typeId: 9);

        self::assertSame(10000, $this->service()->createBalancedForDonor($donor, [$project]));
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * A period the allocator will actually look at. createPeriod() defaults processing to
     * false, and — more subtly — createProject() hands back a fresh ArrayCollection, so
     * persisting a Period never populates the inverse side. Without the explicit add(),
     * createBalancedForDonor() iterates an empty $project->periods and finds no candidates.
     */
    private function processingPeriod(Project $project, int $month = 1): Period
    {
        $period = $this->createPeriod($project, month: $month);
        $period->processing = true;
        $this->em()->flush();
        $project->periods->add($period);

        return $period;
    }

    /** @return array{0: Project, 1: Period} */
    private function mspProject(): array
    {
        // The school/uni gate only fires for project code 'MSP'.
        $project = $this->createProject('MSP');

        return [$project, $this->processingPeriod($project)];
    }

    private function beneficiaryAtSchoolOfType(Project $project, Period $period, int $typeId): void
    {
        $school = $this->createSchool($this->createCity(), $this->createSchoolType($typeId));
        $beneficiary = $this->createBeneficiary('At school type ' . $typeId, school: $school);
        $this->createBeneficiaryPaymentMethod($beneficiary, type: self::BANK);
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);
    }

    /**
     * Only what this run produced: the fixtures above seed prior spend as CONFIRMED, so
     * filtering on NEW keeps setup out of the assertions.
     *
     * @return Transaction[]
     */
    private function newTransactions(Donor $donor): array
    {
        return $this->em()->getRepository(Transaction::class)
            ->findBy(['donor' => $donor, 'status' => Transaction::STATUS_NEW], ['id' => 'ASC']);
    }

    private function service(): TransactionService
    {
        $em = $this->em();

        $validator = new TransactionValidator(
            new CsrfTrueStub(),
            new DonorRepository($em),
            new BeneficiaryRepository($em),
        );

        return new TransactionService(
            new TransactionRepository($em),
            $this->createStub(Session::class),
            new NullLogger(),
            new TransactionFilter($validator),
            $this->createStub(ProjectService::class),
            // Real, not stubbed: period -> beneficiary resolution and its ordering are
            // part of what these tests exercise.
            new BeneficiaryRepository($em),
            // Unused by createBalancedForDonor, but the constructor requires it.
            $this->createStub(PeriodRepository::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
