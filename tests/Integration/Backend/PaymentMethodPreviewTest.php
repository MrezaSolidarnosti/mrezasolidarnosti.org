<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Backend\Controller\TransactionController;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;

/**
 * The figures an admin sees before creating a transaction by hand.
 *
 * `getPaymentMethodPreview` answers two questions for the create form: how would these two
 * pay each other, and how much can move between them. The second one re-derives the same
 * three constraints the cron's allocator uses — donor's remaining pledge, what the
 * beneficiary still needs this period, and the 30,000 per-person cap — in a second place,
 * with nothing tying the two together.
 *
 * It matters more than a preview usually would: `Transaction\Validator\Transaction` checks
 * only that the pair share a payment type, and still carries a `@TODO verify that donor has
 * entered more than entered amount in the form`. So **nothing enforces `maxAmount` on
 * submit** — this number is the only thing between an admin and an over-limit donation.
 *
 * Two deliberate divergences from the allocator are pinned at the bottom rather than
 * treated as bugs, because deciding what to do about them is a product call.
 */
#[CoversClass(TransactionController::class)]
final class PaymentMethodPreviewTest extends TransactionControllerTestCase
{
    private const ACCOUNT = '000000000000000098';
    private const INSTRUCTIONS = 'IBAN RS35000000000000000098, SWIFT TESTRS22';

    private Project $project;
    private Period $period;
    private Donor $donor;
    private Beneficiary $beneficiary;

    // ---- how would they pay each other -------------------------------------------

    public function testItReportsTheMatchingTypeAndTheAccountToPayInto(): void
    {
        $this->given();

        $data = $this->preview()['data'];

        self::assertSame(PaymentMethod::TYPE_BANK_TRANSFER, $data['paymentType']);
        self::assertSame(self::ACCOUNT, $data['accountNumber']);
        self::assertNull($data['instructions']);
        self::assertSame('Bankovni transfer (lokalni)', $data['paymentTypeLabel']);
    }

    public function testAWireMatchCarriesTheInstructionsInsteadOfAnAccountNumber(): void
    {
        $this->given(donorType: PaymentMethod::TYPE_WIRE_TRANSFER, beneficiaryType: PaymentMethod::TYPE_WIRE_TRANSFER);

        $data = $this->preview()['data'];

        self::assertSame(PaymentMethod::TYPE_WIRE_TRANSFER, $data['paymentType']);
        self::assertNull($data['accountNumber']);
        self::assertSame(self::INSTRUCTIONS, $data['instructions']);
        self::assertSame('Bankovni transfer (međunarodni)', $data['paymentTypeLabel']);
    }

    public function testAPairWithNoSharedPaymentTypeIsReportedRatherThanPreviewedAsZero(): void
    {
        // The admin has to be told these two cannot be connected at all — a preview showing
        // 0 would read as "nothing left to give", which is a different problem.
        $this->given(donorType: PaymentMethod::TYPE_BANK_TRANSFER, beneficiaryType: PaymentMethod::TYPE_WIRE_TRANSFER);

        $response = $this->preview();

        self::assertFalse($response['success']);
        self::assertSame('No matching payment type found', $response['message']);
    }

    public function testTheMatchOnlyConsidersThePledgeForTheProjectBeingDonatedTo(): void
    {
        // Why matchPaymentType() takes a project at all. A donor pledging by bank transfer
        // to one project and by wire to another must be previewed on the type belonging to
        // the project on the form — otherwise the instructions sent out are the wrong ones.
        $this->given();
        $other = $this->createProject('MSPR');
        $this->createDonorPaymentMethod($this->donor, $other, PaymentMethod::TYPE_WIRE_TRANSFER, false, 300);
        $this->createBeneficiaryPaymentMethod($this->beneficiary, PaymentMethod::TYPE_WIRE_TRANSFER, null, self::INSTRUCTIONS);

        $data = $this->preview(['periodId' => '0'], project: $other)['data'];

        self::assertSame(PaymentMethod::TYPE_WIRE_TRANSFER, $data['paymentType']);
    }

    public function testAnIdThatMatchesNoRowIsReported(): void
    {
        $this->given();

        $controller = $this->controller(beneficiary: null, donor: $this->donor, project: $this->project);
        $controller->setRequest($this->get('/transaction/getPaymentMethodPreview/', [
            'donorId' => '1', 'beneficiaryId' => '999999', 'projectId' => '1', 'periodId' => '1',
        ]));

        $response = $this->decode($controller->getPaymentMethodPreview());

        self::assertFalse($response['success']);
        self::assertSame('Donor or beneficiary not found.', $response['message']);
    }

    // ---- how much can move -----------------------------------------------------------

    public function testTheMaximumIsTheDonorsRemainingPledgeWhenThatIsTheSmallest(): void
    {
        $this->given(donorAmount: 5000, periodAmount: 50000);

        $data = $this->preview()['data'];

        self::assertSame(5000, $data['donorLeftover']);
        self::assertSame(50000, $data['beneficiaryLeftover']);
        self::assertSame(Transaction::PER_PERSON_LIMIT, $data['perPersonLeftover']);
        self::assertSame(5000, $data['maxAmount']);
    }

    public function testTheMaximumIsWhatTheBeneficiaryStillNeedsWhenThatIsTheSmallest(): void
    {
        $this->given(donorAmount: 100000, periodAmount: 3000);

        $data = $this->preview()['data'];

        self::assertSame(3000, $data['beneficiaryLeftover']);
        self::assertSame(3000, $data['maxAmount']);
    }

    public function testTheMaximumIsThePerPersonCapWhenBothOtherLimitsAreLarger(): void
    {
        $this->given(donorAmount: 100000, periodAmount: 100000);

        $data = $this->preview()['data'];

        self::assertSame(Transaction::PER_PERSON_LIMIT, $data['maxAmount']);
    }

    public function testWhatTheDonorAlreadyGaveToThisProjectIsSubtractedFromTheirPledge(): void
    {
        $this->given(donorAmount: 20000, periodAmount: 100000);
        // To someone else, so only the donor's own budget is touched, not the per-person cap.
        $this->createTransaction(
            $this->donor, $this->createBeneficiary('Someone else'), $this->project, $this->period, 8000,
        );

        $data = $this->preview()['data'];

        self::assertSame(12000, $data['donorLeftover']);
        self::assertSame(12000, $data['maxAmount']);
    }

    public function testACancelledDonationGivesTheDonorTheirBudgetBack(): void
    {
        $this->given(donorAmount: 20000, periodAmount: 100000);
        $this->createTransaction(
            $this->donor, $this->createBeneficiary('Someone else'), $this->project, $this->period, 8000,
            Transaction::STATUS_CANCELLED,
        );

        self::assertSame(20000, $this->preview()['data']['donorLeftover']);
    }

    public function testWhatTheBeneficiaryAlreadyReceivedThisPeriodIsSubtracted(): void
    {
        $this->given(donorAmount: 100000, periodAmount: 10000);
        // From a different donor, so the per-person cap for this pair is untouched.
        $this->createTransaction(
            $this->createDonor(), $this->beneficiary, $this->project, $this->period, 4000,
        );

        $data = $this->preview()['data'];

        self::assertSame(6000, $data['beneficiaryLeftover']);
        self::assertSame(6000, $data['maxAmount']);
    }

    public function testThePerPersonCapCountsWhatWasGivenThroughOtherProjects(): void
    {
        // The cap is per donor-beneficiary pair across everything, not per project — the one
        // limit of the three that the project/period filters do not narrow.
        $this->given(donorAmount: 100000, periodAmount: 100000);
        $otherProject = $this->createProject('MSPR');
        $otherPeriod = $this->createPeriod($otherProject);
        $this->createTransaction($this->donor, $this->beneficiary, $otherProject, $otherPeriod, 25000);

        $data = $this->preview()['data'];

        self::assertSame(100000, $data['donorLeftover']);
        self::assertSame(100000, $data['beneficiaryLeftover']);
        self::assertSame(5000, $data['perPersonLeftover']);
        self::assertSame(5000, $data['maxAmount']);
    }

    public function testAPledgeInEurosIsComparedInDinars(): void
    {
        // Bank transfer amounts are RSD; every other type is EUR and has to be converted
        // before it can be compared with a period allocation or the per-person cap.
        $this->given(
            donorType: PaymentMethod::TYPE_WIRE_TRANSFER,
            donorAmount: 100,
            beneficiaryType: PaymentMethod::TYPE_WIRE_TRANSFER,
            periodAmount: 1000000,
        );

        $data = $this->preview()['data'];

        self::assertSame(Transaction::eurToRsd(100), $data['donorLeftover']);
        self::assertSame(11750, $data['maxAmount']);
    }

    public function testAnExhaustedPledgeShowsZeroRatherThanANegativeNumber(): void
    {
        // The form would happily render a negative maximum, and min() of a negative is a
        // negative — so the flooring has to happen on each limit, not just on the result.
        $this->given(donorAmount: 5000, periodAmount: 100000);
        $this->createTransaction(
            $this->donor, $this->createBeneficiary('Someone else'), $this->project, $this->period, 8000,
        );

        $data = $this->preview()['data'];

        self::assertSame(0, $data['donorLeftover']);
        self::assertSame(0, $data['maxAmount']);
    }

    public function testABeneficiaryNotRegisteredForTheChosenPeriodHasNothingOutstanding(): void
    {
        // getAmountForPeriod() returns 0 for an unregistered period rather than throwing, so
        // picking the wrong period in the form shows 0 — not an error, and not a free-for-all.
        $this->given(donorAmount: 20000, periodAmount: 50000);
        $unregistered = $this->createPeriod($this->project, month: 7);

        $data = $this->preview(period: $unregistered)['data'];

        self::assertSame(0, $data['beneficiaryLeftover']);
        self::assertSame(0, $data['maxAmount']);
    }

    // ---- when the form is only half filled in -------------------------------------------

    public function testTheLimitsAreOmittedUntilAPeriodIsChosen(): void
    {
        // The payment type is answerable from the donor and beneficiary alone; the limits
        // are not, and the form has to be able to preview the first without the second.
        $this->given();

        $data = $this->preview(['periodId' => '0'])['data'];

        self::assertSame(PaymentMethod::TYPE_BANK_TRANSFER, $data['paymentType']);
        self::assertArrayNotHasKey('maxAmount', $data);
        self::assertArrayNotHasKey('donorLeftover', $data);
    }

    public function testWithNoProjectChosenTheTypeIsMatchedAgainstEveryPledgeTheDonorHas(): void
    {
        $this->given();

        $data = $this->preview(['projectId' => '0', 'periodId' => '0'])['data'];

        self::assertSame(PaymentMethod::TYPE_BANK_TRANSFER, $data['paymentType']);
        self::assertArrayNotHasKey('maxAmount', $data);
    }

    // ---- where the preview and the allocator disagree --------------------------------------

    public function testThePreviewDoesNotApplyTheFiveHundredDinarFloorTheAllocatorEnforces(): void
    {
        // allocateToBeneficiary() skips anything under MIN_TRANSACTION_DONATION_AMOUNT
        // because a 200 RSD bank transfer costs more effort than it delivers. The preview
        // has no such floor, and neither does the validator — so an admin is shown 200 as a
        // valid amount and can create the transaction the cron would have refused to make.
        $this->given(donorAmount: 200, periodAmount: 100000);

        $maxAmount = $this->preview()['data']['maxAmount'];

        self::assertSame(200, $maxAmount);
        self::assertLessThan(500, $maxAmount, 'the allocator would have skipped this entirely');
    }

    public function testThePreviewIgnoresTheDonorsSchoolVersusUniversityPreference(): void
    {
        // On MSP the allocator skips beneficiaries whose school type contradicts
        // Donor::$wantsToDonateTo. The preview does not consult it, so a donor who asked to
        // support schools is still shown a full budget against a university beneficiary.
        // Defensible — an admin creating a transaction by hand is making an exception on
        // purpose — but it is a divergence, and it is silent.
        $university = $this->createSchoolType(9, 'Fakultet');
        $this->given(donorAmount: 20000, periodAmount: 100000);
        $this->beneficiary->school = $this->createSchool($this->createCity(), $university, 'Fakultet Test');
        $this->em()->flush();
        $this->donor->wantsToDonateTo = Donor::DONATE_TO_SCHOOL;
        $this->em()->flush();

        self::assertSame(20000, $this->preview()['data']['maxAmount']);
    }

    // ---- fixtures ---------------------------------------------------------------------------

    /**
     * A donor pledging to one project, and a beneficiary registered for one period of it.
     *
     * @param int $donorType       payment type the donor pledges through
     * @param int $donorAmount     pledged amount — RSD for bank transfer, EUR for every other type
     * @param int $beneficiaryType payment type the beneficiary accepts
     * @param int $periodAmount    what the beneficiary is registered for this period
     */
    private function given(
        int $donorType = PaymentMethod::TYPE_BANK_TRANSFER,
        int $donorAmount = 20000,
        int $beneficiaryType = PaymentMethod::TYPE_BANK_TRANSFER,
        int $periodAmount = 50000,
    ): void {
        $this->project = $this->createProject();
        $this->period = $this->createPeriod($this->project);
        $this->donor = $this->createDonor();
        $this->beneficiary = $this->createBeneficiary();

        $this->createDonorPaymentMethod($this->donor, $this->project, $donorType, false, $donorAmount);
        $isBank = $beneficiaryType === PaymentMethod::TYPE_BANK_TRANSFER;
        $this->createBeneficiaryPaymentMethod(
            $this->beneficiary,
            $beneficiaryType,
            $isBank ? self::ACCOUNT : null,
            $isBank ? null : self::INSTRUCTIONS,
        );
        $this->createRegisteredPeriod($this->beneficiary, $this->project, $this->period, $periodAmount);
    }

    /**
     * The lookup services are stubbed by id-insensitive `getById`, so a test that wants a
     * *different* project or period has to say so here as well as in the query string —
     * passing only the id would quietly go on previewing the default one.
     *
     * @param array<string, string> $params overrides for the four query parameters
     * @return array<string, mixed> the decoded JSON envelope
     */
    private function preview(array $params = [], ?Project $project = null, ?Period $period = null): array
    {
        $controller = $this->controller(
            donor: $this->donor,
            beneficiary: $this->beneficiary,
            project: $project ?? $this->project,
            period: $period ?? $this->period,
        );

        // The ids therefore only have to be truthy — except where a test deliberately passes
        // '0' to model an unfilled field, which is what the endpoint's own guards key on.
        $controller->setRequest($this->get('/transaction/getPaymentMethodPreview/', $params + [
            'donorId' => (string) $this->donor->getId(),
            'beneficiaryId' => (string) $this->beneficiary->getId(),
            'projectId' => (string) ($project ?? $this->project)->getId(),
            'periodId' => (string) ($period ?? $this->period)->getId(),
        ]));

        return $this->decode($controller->getPaymentMethodPreview());
    }
}
