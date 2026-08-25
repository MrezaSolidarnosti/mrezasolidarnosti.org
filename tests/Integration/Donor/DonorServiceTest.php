<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Donor\Entity\Donor;
use Skeletor\Login\Service\MagicLinkService;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonorDonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as DonorProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Validator\Donor as DonorValidator;
use Solidarity\Donor\Validator\DonorDonationData as DonorDonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as DonorProfileDataValidator;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project as ProjectService;
use Skeletor\Core\Security\Csrf;

#[CoversClass(DonorService::class)]
final class DonorServiceTest extends IntegrationTestCase
{
    /** @var list<array{string, string, bool}> calls to MagicLinkService::requestMagicLink */
    private array $linksIssued = [];

    /** @var list<array{string, ?string, string}> calls to Mailer::sendDonorLoginMail */
    private array $mailsSent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->linksIssued = [];
        $this->mailsSent = [];
    }

    public function testCreateInsertsNewDonor(): void
    {
        $project = $this->createProject('MSPR');
        $service = $this->donorService();

        $service->create($this->donorData('new@example.com', 'Ada', $project));

        $this->em()->clear();
        $donors = $this->em()->getRepository(Donor::class)->findBy(['email' => 'new@example.com']);

        self::assertCount(1, $donors);
        self::assertSame('Ada', $donors[0]->firstName);
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $project = $this->createProject('MSPR');
        $service = $this->donorService();

        $service->create($this->donorData('dup@example.com', 'Ada', $project));

        // Registering again with the same email is rejected (the donor logs in via a
        // magic link instead); the frontend turns this into a user-facing error.
        try {
            $service->create($this->donorData('dup@example.com', 'Grace', $project));
            self::fail('Expected a "Donor already exists" exception.');
        } catch (\Exception $e) {
            self::assertSame('Donor already exists', $e->getMessage());
        }

        $this->em()->clear();
        $donors = $this->em()->getRepository(Donor::class)->findBy(['email' => 'dup@example.com']);

        self::assertCount(1, $donors);            // no duplicate created
        self::assertSame('Ada', $donors[0]->firstName); // original record untouched
    }

    // ---- the login link ---------------------------------------------------------------

    public function testAKnownDonorIsMailedALoginLink(): void
    {
        $donor = $this->createDonor('known@example.com');
        $service = $this->donorService();

        $service->requestLoginLink('known@example.com');

        self::assertSame([['known@example.com', 'donor', false]], $this->linksIssued);
        self::assertSame([['known@example.com', $donor->getDisplayName(), 'issued-token']], $this->mailsSent);
    }

    public function testAnAddressNobodyRegisteredIsAnsweredWithSilence(): void
    {
        // Deliberately unlike the delegate login, which distinguishes unknown from inactive
        // in its flash messages. This form is on the public site, so telling a stranger
        // whether an address belongs to a donor would leak the donor list one guess at a
        // time. The caller shows the same "check your mail" either way.
        $this->donorService()->requestLoginLink('stranger@example.com');

        self::assertSame([], $this->linksIssued);
        self::assertSame([], $this->mailsSent);
    }

    public function testTheLinkIsIssuedWithoutTheServiceMailingItItself(): void
    {
        // The third argument is $sendEmail = false: MagicLinkService would otherwise send
        // the framework's generic mail, and the donor would get two — one of them wrong,
        // since it points at the dashboard rather than /donor/verifyEmail.
        $this->createDonor('known@example.com');

        $this->donorService()->requestLoginLink('known@example.com');

        self::assertFalse($this->linksIssued[0][2]);
    }

    // ---- the donor table ----------------------------------------------------------------

    public function testTheTableShowsWhatWasPledgedPerProjectInDinars(): void
    {
        // prepareEntities() builds the rows an admin reads. Every non-bank pledge is held in
        // EUR, so a row that skipped the conversion would understate a wire donor by 117x.
        $msp = $this->createProject('MSP');
        $donor = $this->createDonor('table@example.com');
        $this->createDonorPaymentMethod($donor, $msp, type: 1, amount: 5000);
        $this->createDonorPaymentMethod($donor, $msp, type: 2, amount: 100);

        // Both pledges are for one project, so they are summed under its code.
        self::assertSame('MSP (16,750)', $this->row($donor)['pledgedAmount']);
    }

    public function testEachProjectGetsItsOwnPledgeFigure(): void
    {
        $msp = $this->createProject('MSP');
        $mspr = $this->createProject('MSPR');
        $donor = $this->createDonor('two@example.com');
        $this->createDonorPaymentMethod($donor, $msp, type: 1, amount: 5000);
        $this->createDonorPaymentMethod($donor, $mspr, type: 1, amount: 3000);

        $row = $this->row($donor);

        self::assertStringContainsString('MSP (5,000)', $row['pledgedAmount']);
        self::assertStringContainsString('MSPR (3,000)', $row['pledgedAmount']);
    }

    public function testOnlyMoneyThatActuallyMovedCountsAsPaid(): void
    {
        // Confirmed and paid are real; new is an instruction nobody has acted on and
        // cancelled is one that will not happen. Counting either would tell an admin a donor
        // had given money they have not.
        $msp = $this->createProject('MSP');
        $period = $this->createPeriod($msp);
        $donor = $this->createDonor('paid@example.com');
        $beneficiary = $this->createBeneficiary();

        $this->createTransaction($donor, $beneficiary, $msp, $period, 5000, Transaction::STATUS_CONFIRMED);
        $this->createTransaction($donor, $beneficiary, $msp, $period, 2000, Transaction::STATUS_PAID);
        $this->createTransaction($donor, $beneficiary, $msp, $period, 9000, Transaction::STATUS_NEW);
        $this->createTransaction($donor, $beneficiary, $msp, $period, 4000, Transaction::STATUS_CANCELLED);

        self::assertSame('MSP (7,000)', $this->row($donor)['paidAmount']);
    }

    public function testADonorWithNoPledgesStillProducesAWholeRow(): void
    {
        // The table renders every key it is given; an absent one is a warning in the
        // dashboard rather than an empty cell.
        $donor = $this->createDonor('bare@example.com');

        $row = $this->row($donor);

        self::assertSame('', $row['pledgedAmount']);
        self::assertSame('', $row['paidAmount']);
        self::assertSame('', $row['paymentMethods']);
        self::assertArrayHasKey('createdAt', $row);
    }

    public function testTheDonorCountIsScopedToAStatus(): void
    {
        // Drives the "how many verified donors" figure; a count that ignored the status
        // would report everyone, including the ones an admin marked as a problem.
        $this->createDonor('verified@example.com', status: Donor::STATUS_VERIFIED);
        $this->createDonor('another@example.com', status: Donor::STATUS_VERIFIED);
        $this->createDonor('problem@example.com', status: Donor::STATUS_PROBLEM);

        $service = $this->donorService();

        self::assertSame(2, $service->getDonorCount(Donor::STATUS_VERIFIED, null));
        self::assertSame(1, $service->getDonorCount(Donor::STATUS_PROBLEM, null));
    }

    /**
     * One rendered table row, by its column keys.
     *
     * The reload is not optional: prepareEntities() formats `createdAt`, which is
     * insertable:false and therefore unset on an entity this test just persisted — reading
     * it would fatal on "must not be accessed before initialization" rather than fail.
     *
     * @return array<string, mixed>
     */
    private function row(Donor $donor): array
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->donorService()->prepareEntities([$this->em()->find(Donor::class, $id)])[0]['columns'];
    }

    /**
     * @return array<string, mixed>
     */
    private function donorData(string $email, string $firstName, $project): array
    {
        return [
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => 'Last',
            'wantsToDonateTo' => Donor::DONATE_TO_ALL,
            'comment' => null,
            'isActive' => 1,
            'projects' => [$project->getId()],
            'status' => Donor::STATUS_VERIFIED,
            'paymentMethods' => [],
            Csrf::TOKEN_NAME => 'token',
        ];
    }

    private function donorService(): DonorService
    {
        $em = $this->em();

        return new DonorService(
            new DonorRepository($em),
            $this->createStub(Session::class),
            new NullLogger(),
            new DonorFilter(new DonorValidator(new CsrfTrueStub())),
            $this->recordingMailer(),
            $this->createStub(ProjectService::class),
            $this->recordingMagicLinkService(),
            $this->createStub(DonorProfileDataFilter::class),
            $this->createStub(DonorProfileDataValidator::class),
            $this->createStub(DonorDonationDataValidator::class),
            $this->createStub(DonorDonationDataFilter::class),
            $this->createStub(TransactionService::class),
            $this->createStub(\Solidarity\Transaction\Service\QrCode::class),
            // Unused by the paths under test; with no language set translate() is a
            // pass-through, so a stub returning null would still be safe.
            $this->createStub(\Skeletor\Translator\Service\Translator::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }

    /** Records issued links; returns a token because the real method declares `: string`. */
    private function recordingMagicLinkService(): MagicLinkService
    {
        $service = $this->createStub(MagicLinkService::class);
        $service->method('requestMagicLink')->willReturnCallback(
            function (string $email, string $entityType = 'user', bool $sendEmail = true): string {
                $this->linksIssued[] = [$email, $entityType, $sendEmail];

                return 'issued-token';
            },
        );

        return $service;
    }

    private function recordingMailer(): Mailer
    {
        $mailer = $this->createStub(Mailer::class);
        $mailer->method('sendDonorLoginMail')->willReturnCallback(
            function (string $email, string $displayName, string $token): void {
                $this->mailsSent[] = [$email, $displayName, $token];
            },
        );

        return $mailer;
    }
}
