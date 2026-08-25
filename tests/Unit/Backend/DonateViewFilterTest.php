<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Backend\Blocks\Donate\DonateViewFilter;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Frontend\Service\Session;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Service\Transaction as TransactionService;

#[CoversClass(DonateViewFilter::class)]
final class DonateViewFilterTest extends TestCase
{
    private const BANK = 1;
    private const WIRE = 2;

    // ---- guests and donors with nothing saved ------------------------------

    public function testAGuestGetsTheDefaultsAndNoLookups(): void
    {
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(false);

        // Whether the network has open needs is nobody's business until we know who is asking.
        $transaction = $this->createMock(TransactionService::class);
        $transaction->expects(self::never())->method('hasUnmetNeeds');

        $data = (new DonateViewFilter($session, $transaction))->filter([]);

        self::assertNull($data['existingProjectId']);
        self::assertSame([], $data['existingPaymentMethods']);
        self::assertFalse($data['hasUnmetNeeds']);
    }

    public function testEveryKeyTheTemplateReadsIsAlwaysPresentForAGuest(): void
    {
        // donate.php reads these unguarded; a missing key is an "Undefined array key"
        // warning on every anonymous page view.
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(false);

        $data = (new DonateViewFilter($session, $this->createStub(TransactionService::class)))->filter([]);

        self::assertArrayHasKey('existingProjectId', $data);
        self::assertArrayHasKey('existingPaymentMethods', $data);
        self::assertArrayHasKey('hasUnmetNeeds', $data);
    }

    public function testALoggedInSessionWithNoResolvableUserFallsBackToTheDefaults(): void
    {
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(true);
        $session->method('getUser')->willReturn(null);

        $data = (new DonateViewFilter($session, $this->createStub(TransactionService::class)))->filter([]);

        self::assertNull($data['existingProjectId']);
        self::assertFalse($data['hasUnmetNeeds']);
    }

    public function testADonorWithNoPledgeStillGetsTheUnmetNeedsHint(): void
    {
        // No saved pledge, but the one-time action is still offered, so the hint matters.
        $data = $this->filterFor($this->donor(), hasUnmetNeeds: true);

        self::assertTrue($data['hasUnmetNeeds']);
        self::assertNull($data['existingProjectId']);
        self::assertSame([], $data['existingPaymentMethods']);
        self::assertArrayNotHasKey('existingProjectName', $data);
    }

    // ---- a donor pledged to one project ------------------------------------

    public function testASingleProjectPledgeReportsThatProject(): void
    {
        $project = $this->project(1, 'Mreza solidarnosti za prosvetu');
        $donor = $this->donor(
            $this->paymentMethod($project, self::BANK, 5000, 1),
        );

        $data = $this->filterFor($donor);

        self::assertSame(1, $data['existingProjectId']);
        self::assertSame('Mreza solidarnosti za prosvetu', $data['existingProjectName']);
        self::assertSame(
            [['type' => self::BANK, 'amount' => 5000, 'currency' => 1]],
            $data['existingPaymentMethods'],
        );
    }

    public function testAllOfTheProjectsMethodsArePrefilled(): void
    {
        $project = $this->project(1, 'MSP');
        $donor = $this->donor(
            $this->paymentMethod($project, self::BANK, 5000, 1),
            $this->paymentMethod($project, self::WIRE, 100, 2),
        );

        $data = $this->filterFor($donor);

        self::assertSame(1, $data['existingProjectId']);
        self::assertSame(
            [
                ['type' => self::BANK, 'amount' => 5000, 'currency' => 1],
                ['type' => self::WIRE, 'amount' => 100, 'currency' => 2],
            ],
            $data['existingPaymentMethods'],
        );
    }

    // ---- a donor pledged to both projects ----------------------------------

    public function testPledgesInTwoProjectsCollapseToTheBothOption(): void
    {
        // -1 is the "oba pravca podrške" card's data-id, so the JS pre-selects it.
        $donor = $this->donor(
            $this->paymentMethod($this->project(1, 'MSP'), self::BANK, 5000, 1),
            $this->paymentMethod($this->project(2, 'MSPR'), self::BANK, 3000, 1),
        );

        $data = $this->filterFor($donor);

        self::assertSame(-1, $data['existingProjectId']);
        self::assertSame('Oba pravca podrške', $data['existingProjectName']);
    }

    public function testOnlyTheFirstProjectsAmountsArePrefilled(): void
    {
        // The form edits one project at a time, so mixing both projects' amounts into the
        // prefill would show the donor numbers they never pledged for that direction.
        $donor = $this->donor(
            $this->paymentMethod($this->project(1, 'MSP'), self::BANK, 5000, 1),
            $this->paymentMethod($this->project(2, 'MSPR'), self::BANK, 3000, 1),
        );

        $data = $this->filterFor($donor);

        self::assertCount(1, $data['existingPaymentMethods']);
        self::assertSame(5000, $data['existingPaymentMethods'][0]['amount']);
    }

    // ---- the unmet-needs hint ----------------------------------------------

    public function testTheUnmetNeedsHintIsScopedToTheDonor(): void
    {
        $donor = $this->donor();

        $transaction = $this->createMock(TransactionService::class);
        $transaction->expects(self::once())
            ->method('hasUnmetNeeds')
            ->with($donor)
            ->willReturn(true);

        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(true);
        $session->method('getUser')->willReturn($donor);

        self::assertTrue((new DonateViewFilter($session, $transaction))->filter([])['hasUnmetNeeds']);
    }

    public function testTheUnmetNeedsHintIsFalseWhenNothingMatches(): void
    {
        self::assertFalse($this->filterFor($this->donor(), hasUnmetNeeds: false)['hasUnmetNeeds']);
    }

    // ---- the filter augments, it does not replace ---------------------------

    public function testUnrelatedBlockDataSurvives(): void
    {
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(false);

        $data = (new DonateViewFilter($session, $this->createStub(TransactionService::class)))
            ->filter(['title' => 'Doniraj', 'blockViewMode' => '0']);

        self::assertSame('Doniraj', $data['title']);
        self::assertSame('0', $data['blockViewMode']);
    }

    // ---- helpers ------------------------------------------------------------

    /** @return array<string, mixed> */
    private function filterFor(Donor $donor, bool $hasUnmetNeeds = false): array
    {
        $session = $this->createStub(Session::class);
        $session->method('isDonor')->willReturn(true);
        $session->method('getUser')->willReturn($donor);

        $transaction = $this->createStub(TransactionService::class);
        $transaction->method('hasUnmetNeeds')->willReturn($hasUnmetNeeds);

        return (new DonateViewFilter($session, $transaction))->filter([]);
    }

    private function donor(PaymentMethod ...$paymentMethods): Donor
    {
        $donor = new Donor();
        foreach ($paymentMethods as $paymentMethod) {
            $donor->paymentMethods->add($paymentMethod);
        }

        return $donor;
    }

    private function project(int $id, string $name): Project
    {
        $project = new Project();
        $project->id = $id;
        $project->name = $name;
        $project->code = $name;
        $project->logo = '';

        return $project;
    }

    private function paymentMethod(Project $project, int $type, int $amount, int $currency): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->project = $project;
        $paymentMethod->type = $type;
        $paymentMethod->amount = $amount;
        $paymentMethod->currency = $currency;
        $paymentMethod->monthly = 1;

        return $paymentMethod;
    }
}
