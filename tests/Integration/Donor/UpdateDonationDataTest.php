<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Login\Service\MagicLinkService;
use Skeletor\Translator\Service\Translator;
use Skeletor\User\Service\Session;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as ProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;

/**
 * The monthly pledge save, end to end through filter -> validator -> repository, with the
 * raw POST shape the donate form actually submits (payment[type][value]).
 *
 * The repository half is covered in DonorRepositoryTest; what only exists here is the
 * validation, and it is the last thing standing between a typo'd form and a pledge the
 * cron will act on twice a week.
 */
#[CoversClass(DonorService::class)]
final class UpdateDonationDataTest extends IntegrationTestCase
{
    private const BANK = 1;
    private const WIRE = 2;
    private const RSD = 1;
    private const EUR = 2;

    public function testSavesAValidDinarPledge(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->service()->updateDonationData($this->post($donor, 1, self::BANK, 5000, self::RSD));

        $donor = $this->reload($donor);
        self::assertCount(1, $donor->paymentMethods);
        self::assertSame(5000, $donor->paymentMethods[0]->amount);
    }

    public function testSavesAValidEuroPledge(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->service()->updateDonationData($this->post($donor, 1, self::WIRE, 100, self::EUR));

        $donor = $this->reload($donor);
        // Stored in the pledged currency; conversion happens at allocation time.
        self::assertSame(100, $donor->paymentMethods[0]->amount);
        self::assertSame(self::EUR, $donor->paymentMethods[0]->currency);
    }

    public function testSavesAPledgeToBothDirections(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $this->createProjectWithId(2, 'MSPR');
        $donor = $this->createDonor();

        $this->service()->updateDonationData($this->post($donor, -1, self::BANK, 5000, self::RSD));

        self::assertCount(2, $this->reload($donor)->projects);
    }

    // ---- what the validator has to catch ------------------------------------

    public function testRejectsAProjectThatDoesNotExist(): void
    {
        $donor = $this->createDonor();

        $this->expectException(ValidatorException::class);

        $this->service()->updateDonationData($this->post($donor, 5, self::BANK, 5000, self::RSD));
    }

    public function testRejectsADinarAmountUnderTheMinimum(): void
    {
        // Below MIN_TRANSACTION_DONATION_AMOUNT the allocator would never use the pledge,
        // so accepting it would leave the donor believing they had given.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->expectException(ValidatorException::class);

        $this->service()->updateDonationData($this->post($donor, 1, self::BANK, 499, self::RSD));
    }

    public function testRejectsAEuroAmountUnderTheMinimum(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->expectException(ValidatorException::class);

        $this->service()->updateDonationData($this->post($donor, 1, self::WIRE, 9, self::EUR));
    }

    public function testRejectsAnUnsupportedPaymentType(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->expectException(ValidatorException::class);

        $this->service()->updateDonationData($this->post($donor, 1, 9, 5000, self::RSD));
    }

    public function testRejectsAMissingAmount(): void
    {
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();

        $this->expectException(ValidatorException::class);

        $this->service()->updateDonationData([
            'donorId' => $donor->getId(),
            'project' => 1,
            'payment' => [self::BANK => ['currency' => self::RSD]],
        ]);
    }

    public function testARejectedSaveLeavesTheExistingPledgeIntact(): void
    {
        // The repository deletes before it writes, so validation failing *after* the
        // delete would wipe a donor who only mistyped an amount. It throws first.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();
        $this->service()->updateDonationData($this->post($donor, 1, self::BANK, 5000, self::RSD));

        try {
            $this->service()->updateDonationData($this->post($donor, 1, self::BANK, 10, self::RSD));
            self::fail('Expected the pledge to be rejected.');
        } catch (ValidatorException) {
            // expected
        }

        self::assertCount(1, $this->reload($donor)->paymentMethods);
    }

    public function testTheRejectionReasonIsReadableByTheAction(): void
    {
        // UpdateDonationData reads these back to build the response, so an empty list
        // would surface as a failed save with no explanation.
        $this->createProjectWithId(1, 'MSP');
        $donor = $this->createDonor();
        $service = $this->service();

        try {
            $service->updateDonationData($this->post($donor, 1, self::BANK, 100, self::RSD));
        } catch (ValidatorException) {
            // expected
        }

        self::assertNotEmpty($service->getDonationDataFilterErrors());
    }

    // ---- helpers ---------------------------------------------------------------

    /** The raw POST body the donate form submits, pre-filter. */
    private function post(Donor $donor, int $projectId, int $type, int $amount, int $currency): array
    {
        return [
            'donorId' => $donor->getId(),
            'project' => $projectId,
            'payment' => [$type => ['value' => $amount, 'currency' => $currency]],
        ];
    }

    private function reload(Donor $donor): Donor
    {
        $id = $donor->getId();
        $this->em()->clear();

        return $this->em()->getRepository(Donor::class)->find($id);
    }

    private function service(): DonorService
    {
        $em = $this->em();

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
            // Real: the validator is the whole point of this file.
            new DonationDataValidator(new CsrfTrueStub()),
            new DonationDataFilter(),
            $this->createStub(TransactionService::class),
            $this->createStub(QrCode::class),
            $this->createStub(Translator::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
