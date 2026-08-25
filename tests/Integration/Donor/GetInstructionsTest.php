<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\Login\Service\MagicLinkService;
use Skeletor\Translator\Service\Translator;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as ProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;

/**
 * The payload behind the donor's "Instrukcije za uplatu" table. It is assembled per row
 * rather than queried, so the shape is easy to break silently — the frontend reads these
 * keys by name and renders blanks for anything missing.
 */
#[CoversClass(DonorService::class)]
final class GetInstructionsTest extends IntegrationTestCase
{
    private const BANK = 1;
    private const WIRE = 2;

    public function testRejectsAnUnknownDonor(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Donor not found');

        $this->fetch(999999);
    }

    public function testADonorWithNoInstructionsGetsAnEmptyPage(): void
    {
        $donor = $this->createDonor();

        $result = $this->fetch($donor->getId());

        self::assertSame([], $result['items']);
        self::assertSame(0, $result['total']);
        self::assertSame(0, $result['totalPages']);
    }

    public function testEveryKeyTheTableRendersIsPresent(): void
    {
        [$donor] = $this->instruction();

        $item = $this->fetch($donor->getId())['items'][0];

        foreach ([
            'id', 'beneficiaryName', 'amount', 'referenceCode', 'createdAt', 'expiresAt',
            'status', 'projectId', 'paymentType', 'accountNumber', 'qrCode',
        ] as $key) {
            self::assertArrayHasKey($key, $item);
        }
    }

    public function testTheAmountIsFormattedAndTheStatusIsLabelled(): void
    {
        [$donor] = $this->instruction(amount: 30000);

        $item = $this->fetch($donor->getId())['items'][0];

        self::assertSame('30,000', $item['amount']);
        self::assertSame(Transaction::STATUS_NEW, $item['status']['value']);
        self::assertSame('Čeka se uplata', $item['status']['label']);
    }

    public function testOnlyAnOpenInstructionCarriesAnExpiry(): void
    {
        // The 72h countdown is meaningless once the donor has reported paying, and the
        // frontend keys its "deadline is expiring" banner off this being non-null.
        [$open] = $this->instruction();
        [$done] = $this->instruction(status: Transaction::STATUS_CONFIRMED);

        self::assertNotNull($this->fetch($open->getId())['items'][0]['expiresAt']);
        self::assertNull($this->fetch($done->getId())['items'][0]['expiresAt']);
    }

    public function testABankTransferGetsAQrCodeAndAWireTransferDoesNot(): void
    {
        // NBS IPS codes are dinar-only; offering one for a SEPA payment would be a QR
        // no bank can act on.
        [$bankDonor] = $this->instruction(paymentType: self::BANK);
        [$wireDonor] = $this->instruction(paymentType: self::WIRE);

        self::assertNotSame('', $this->fetch($bankDonor->getId())['items'][0]['qrCode']);
        self::assertSame('', $this->fetch($wireDonor->getId())['items'][0]['qrCode']);
    }

    public function testInstructionsAreListedNewestFirst(): void
    {
        $donor = $this->createDonor();
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        $older = $this->createTransaction($donor, $beneficiary, $project, $period, 1000);
        $this->backdateTransaction($older, '2020-01-01 00:00:00');
        $newer = $this->createTransaction($donor, $beneficiary, $project, $period, 2000);

        $items = $this->fetch($donor->getId())['items'];

        self::assertSame($newer->getId(), $items[0]['id']);
        self::assertSame($older->getId(), $items[1]['id']);
    }

    // ---- paging --------------------------------------------------------------

    public function testPagingReportsTheTotalAndSplitsTheRows(): void
    {
        $donor = $this->donorWithInstructions(5);

        $first = $this->fetch($donor->getId(), page: 1, perPage: 2);

        self::assertCount(2, $first['items']);
        self::assertSame(5, $first['total']);
        self::assertSame(3, $first['totalPages']);
        self::assertSame(1, $first['page']);
        self::assertSame(2, $first['perPage']);
    }

    public function testTheLastPageHoldsTheRemainder(): void
    {
        $donor = $this->donorWithInstructions(5);

        self::assertCount(1, $this->fetch($donor->getId(), page: 3, perPage: 2)['items']);
    }

    public function testAPageBeyondTheEndIsEmptyRatherThanAnError(): void
    {
        $donor = $this->donorWithInstructions(2);

        self::assertSame([], $this->fetch($donor->getId(), page: 99, perPage: 10)['items']);
    }

    public function testAPageBelowOneIsClampedRatherThanProducingANegativeOffset(): void
    {
        $donor = $this->donorWithInstructions(2);

        $result = $this->fetch($donor->getId(), page: 0, perPage: 10);

        self::assertSame(1, $result['page']);
        self::assertCount(2, $result['items']);
    }

    // ---- helpers ---------------------------------------------------------------

    /** @return array{0: Donor, 1: Transaction} */
    private function instruction(
        int $amount = 5000,
        int $status = Transaction::STATUS_NEW,
        int $paymentType = self::BANK,
    ): array {
        $donor = $this->createDonor();
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        $transaction = $this->createTransaction(
            $donor,
            $beneficiary,
            $project,
            $period,
            $amount,
            $status,
            paymentType: $paymentType,
        );

        return [$donor, $transaction];
    }

    private function donorWithInstructions(int $count): Donor
    {
        $donor = $this->createDonor();
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $beneficiary = $this->createBeneficiary();

        for ($i = 0; $i < $count; $i++) {
            $this->createTransaction($donor, $beneficiary, $project, $period, 1000 + $i);
        }

        return $donor;
    }

    /**
     * Always reads through a cleared identity map, because createdAt is insertable:false:
     * Doctrine neither writes it on INSERT nor reads it back, so a just-persisted
     * Transaction still in memory has that typed property uninitialised and
     * getCreatedAt() fatals. Production only ever lists instructions in a later request,
     * against entities loaded from the database — clearing makes the test do the same.
     *
     * @return array<string, mixed>
     */
    private function fetch(int $donorId, int $page = 1, int $perPage = 10): array
    {
        $this->em()->clear();

        return $this->service()->getInstructions($donorId, $page, $perPage);
    }

    /**
     * A bare stub returns '' for translate(), which silently blanks the status label. This
     * mirrors the real service on the default locale instead: with no language set,
     * Translator::translate() hands back the string it was given.
     */
    private function passThroughTranslator(): Translator
    {
        $translator = $this->createStub(Translator::class);
        $translator->method('translate')->willReturnArgument(0);

        return $translator;
    }

    private function service(): DonorService
    {
        $em = $this->em();

        $transactionFilter = new TransactionFilter(new TransactionValidator(
            new CsrfTrueStub(),
            new DonorRepository($em),
            new BeneficiaryRepository($em),
        ));

        return new DonorService(
            new DonorRepository($em),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(DonorFilter::class),
            $this->createStub(Mailer::class),
            $this->createStub(ProjectService::class),
            $this->createStub(MagicLinkService::class),
            $this->createStub(ProfileDataFilter::class),
            $this->createStub(ProfileDataValidator::class),
            new DonationDataValidator(new CsrfTrueStub()),
            new DonationDataFilter(),
            new TransactionService(
                new TransactionRepository($em),
                $this->createStub(Session::class),
                new NullLogger(),
                $transactionFilter,
                $this->createStub(ProjectService::class),
                new BeneficiaryRepository($em),
                new PeriodRepository($em),
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
            // Real: whether a row gets a QR is part of the payload contract.
            new QrCode(),
            $this->passThroughTranslator(),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
