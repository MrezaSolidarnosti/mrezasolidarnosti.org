<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use Skeletor\Core\Config\Config;
use Laminas\Session\ManagerInterface;
use Laminas\Session\Storage\ArrayStorage;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Solidarity\Backend\Action\Statistics;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

/**
 * Exercises the dashboard aggregate math. getStats() is private and __invoke()
 * renders a template, so we call getStats() via reflection against a real,
 * seeded database.
 */
#[CoversClass(Statistics::class)]
final class StatisticsTest extends IntegrationTestCase
{
    public function testDonorCountExcludesDeletedDonors(): void
    {
        $this->createDonor(status: Donor::STATUS_VERIFIED);
        $this->createDonor(status: Donor::STATUS_NEW);
        $this->createDonor(status: Donor::STATUS_DELETED);

        self::assertSame(2, $this->stats(null)['donorCount']);
    }

    public function testDelegateCountIncludesOnlyNewAndVerified(): void
    {
        $this->createDelegate(Delegate::STATUS_NEW);
        $this->createDelegate(Delegate::STATUS_VERIFIED);
        $this->createDelegate(Delegate::STATUS_PROBLEM);

        self::assertSame(2, $this->stats(null)['delegateCount']);
    }

    public function testConfirmedAmountSumsOnlyConfirmedTransactions(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $project, $period, 3000, Transaction::STATUS_NEW);

        $stats = $this->stats(null);

        self::assertSame(5000, $stats['confirmedAmount']);
        self::assertSame(1, $stats['confirmedCount']);
    }

    public function testTotalPledgedConvertsEurPaymentMethodsToRsd(): void
    {
        $project = $this->createProject('MSPR');
        $donor = $this->createDonor();
        // 5000 RSD (bank transfer) + 100 EUR (wire) -> 5000 + 100*117.5
        $this->createDonorPaymentMethod($donor, $project, type: 1, amount: 5000);
        $this->createDonorPaymentMethod($donor, $project, type: 2, amount: 100);

        self::assertSame(5000 + 11750, $this->stats(null)['totalPledged']);
    }

    public function testProjectScopedStatsCountOnlyThatProject(): void
    {
        $projectA = $this->createProject('MSPR');
        $periodA = $this->createPeriod($projectA);
        $projectB = $this->createProject('MSP');
        $periodB = $this->createPeriod($projectB);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $projectA, $periodA, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $projectB, $periodB, 8000, Transaction::STATUS_CONFIRMED);

        self::assertSame(5000, $this->stats($projectA)['confirmedAmount']);
    }

    // ---- who is counted --------------------------------------------------------------

    public function testTheMonthlyDonorCountCountsPeopleNotPledges(): void
    {
        // "How many people give every month" — a donor pledging to two projects, or by two
        // methods, is still one person. COUNT(DISTINCT pm.donor) is what makes that true.
        $msp = $this->createProject('MSP');
        $mspr = $this->createProject('MSPR');
        $regular = $this->createDonor();
        $this->createDonorPaymentMethod($regular, $msp, monthly: true, amount: 3000);
        $this->createDonorPaymentMethod($regular, $mspr, monthly: true, amount: 2000);

        $oneOff = $this->createDonor();
        $this->createDonorPaymentMethod($oneOff, $msp, monthly: false, amount: 9000);

        self::assertSame(1, $this->stats(null)['monthlyDonorCount']);
    }

    public function testADeletedDonorDropsOutOfTheMonthlyCountToo(): void
    {
        $project = $this->createProject('MSP');
        $deleted = $this->createDonor(status: Donor::STATUS_DELETED);
        $this->createDonorPaymentMethod($deleted, $project, monthly: true, amount: 3000);

        self::assertSame(0, $this->stats(null)['monthlyDonorCount']);
    }

    public function testBeneficiariesAreCountedWhateverTheirStatus(): void
    {
        // Deliberate, and the opposite of the donor rule: a removed beneficiary stays in the
        // headline figure because the money that reached them is still in the totals beside
        // it. The comment in getBeneficiaryCount() says so; this stops it being "tidied".
        $this->createBeneficiary('Active');
        $this->createBeneficiary('Removed', status: Beneficiary::STATUS_DELETED);

        self::assertSame(2, $this->stats(null)['beneficiaryCount']);
    }

    public function testScopingToAProjectCountsOnlyBeneficiariesRegisteredForIt(): void
    {
        $msp = $this->createProject('MSP');
        $mspr = $this->createProject('MSPR');
        $ours = $this->createBeneficiary('Ours');
        $theirs = $this->createBeneficiary('Theirs');
        $this->createRegisteredPeriod($ours, $msp, $this->createPeriod($msp), 1000);
        $this->createRegisteredPeriod($theirs, $mspr, $this->createPeriod($mspr), 1000);

        self::assertSame(1, $this->stats($msp)['beneficiaryCount']);
    }

    // ---- what was pledged -------------------------------------------------------------

    public function testMonthlyPledgedCountsOnlyTheStandingPledges(): void
    {
        // The difference between this and totalPledged is what the cron can rely on month
        // after month, versus what has been promised once.
        $project = $this->createProject('MSP');
        $donor = $this->createDonor();
        $this->createDonorPaymentMethod($donor, $project, type: 1, monthly: true, amount: 4000);
        $this->createDonorPaymentMethod($donor, $project, type: 2, monthly: true, amount: 100);
        $this->createDonorPaymentMethod($donor, $this->createProject('MSPR'), type: 1, monthly: false, amount: 50000);

        $stats = $this->stats(null);

        self::assertSame(4000 + 11750, $stats['monthlyPledged']);
        self::assertSame(4000 + 11750 + 50000, $stats['totalPledged']);
    }

    public function testADeletedDonorHasNoPledgeLeftToCount(): void
    {
        // A pledge is a promise about the future, so a deleted donor has none — the two
        // pledge sums exclude them exactly as the headcounts do. Money they already sent is
        // a different question; see the transaction test below.
        $project = $this->createProject('MSP');
        $this->createDonorPaymentMethod($this->createDonor(), $project, type: 1, monthly: true, amount: 5000);
        $deleted = $this->createDonor(status: Donor::STATUS_DELETED);
        $this->createDonorPaymentMethod($deleted, $project, type: 1, monthly: true, amount: 7000);

        $stats = $this->stats(null);

        self::assertSame(1, $stats['donorCount']);
        self::assertSame(5000, $stats['totalPledged']);
        self::assertSame(5000, $stats['monthlyPledged']);
    }

    public function testMoneyADeletedDonorAlreadySentStillCounts(): void
    {
        // The other half of the rule, and the reason the transaction queries deliberately do
        // not filter on donor status: the network really did move this money, and the
        // period and project totals have to keep adding up after someone is removed.
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);
        $deleted = $this->createDonor(status: Donor::STATUS_DELETED);
        $this->createDonorPaymentMethod($deleted, $project, type: 1, amount: 7000);
        $this->createTransaction($deleted, $this->createBeneficiary(), $project, $period, 6000, Transaction::STATUS_CONFIRMED);

        $stats = $this->stats(null);

        self::assertSame(0, $stats['totalPledged'], 'the promise is gone');
        self::assertSame(6000, $stats['confirmedAmount'], 'the money is not');
    }

    // ---- transaction buckets -------------------------------------------------------------

    public function testEveryTransactionStatusGetsItsOwnAmountAndCount(): void
    {
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $project, $period, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $project, $period, 7000, Transaction::STATUS_PAID);
        $this->createTransaction($donor, $beneficiary, $project, $period, 1000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $project, $period, 2000, Transaction::STATUS_CANCELLED);
        $this->createTransaction($donor, $beneficiary, $project, $period, 3000, Transaction::STATUS_EXPIRED);

        $stats = $this->stats(null);

        self::assertSame([5000, 1], [$stats['confirmedAmount'], $stats['confirmedCount']]);
        self::assertSame([7000, 1], [$stats['paidAmount'], $stats['paidCount']]);
        self::assertSame([1000, 1], [$stats['activeAmount'], $stats['activeCount']]);
        self::assertSame([2000, 1], [$stats['cancelledAmount'], $stats['cancelledCount']]);
        self::assertSame([3000, 1], [$stats['expiredAmount'], $stats['expiredCount']]);
    }

    public function testAnEmptyDashboardReportsZerosRatherThanNulls(): void
    {
        // Every figure goes straight into the template. COALESCE is what keeps a fresh
        // install from rendering blanks where the numbers should be.
        foreach ($this->stats(null) as $key => $value) {
            self::assertSame(0, $value, $key);
        }
    }

    // ---- the per-period breakdown ----------------------------------------------------------

    public function testThePerPeriodBreakdownIsScopedToItsOwnPeriod(): void
    {
        // Rendered as the round-by-round table. It takes both a project and a period, and
        // the two filters have to apply together — a project-only filter would report every
        // round's money under each round.
        $project = $this->createProject('MSP');
        $thisRound = $this->createPeriod($project, month: 3);
        $lastRound = $this->createPeriod($project, month: 2);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary();
        $this->createRegisteredPeriod($beneficiary, $project, $thisRound, 40000);

        $this->createTransaction($donor, $beneficiary, $project, $thisRound, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $project, $lastRound, 9000, Transaction::STATUS_CONFIRMED);

        $stats = $this->periodStats($project, $thisRound);

        self::assertSame(5000, $stats['confirmedAmount']);
        self::assertSame(1, $stats['confirmedCount']);
        self::assertSame(1, $stats['beneficiaryCount']);
    }

    public function testAPeriodNobodyHasBeenPaidForYetStillReportsAWholeRow(): void
    {
        $project = $this->createProject('MSP');
        $period = $this->createPeriod($project);

        $stats = $this->periodStats($project, $period);

        foreach (['confirmed', 'paid', 'active', 'cancelled'] as $bucket) {
            self::assertSame(0, $stats[$bucket . 'Amount'], $bucket);
            self::assertSame(0, $stats[$bucket . 'Count'], $bucket);
        }
        self::assertSame(0, $stats['beneficiaryCount']);
    }

    /** @return array<string, int> */
    private function periodStats(Project $project, Period $period): array
    {
        return (new ReflectionMethod(Statistics::class, 'getTransactionStatsByPeriod'))
            ->invoke($this->statisticsAction(), $project, $period);
    }

    /**
     * @return array<string, int>
     */
    private function stats(?Project $project): array
    {
        $method = new ReflectionMethod(Statistics::class, 'getStats');

        return $method->invoke($this->statisticsAction(), $project);
    }

    private function statisticsAction(): Statistics
    {
        $storage = new ArrayStorage([
            'loggedIn' => null,
            'loggedInEmail' => null,
            'loggedInRole' => null,
            'loggedInEntityType' => null,
        ]);
        $session = $this->createStub(ManagerInterface::class);
        $session->method('getStorage')->willReturn($storage);

        return new Statistics(
            new NullLogger(),
            new Config([]),
            new Engine(),
            $this->em(),
            $session,
        );
    }
}
