<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Donor;

use Skeletor\Core\Config\Config;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use Skeletor\Login\Service\MagicLinkService;
use Skeletor\Translator\Service\Translator;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Filter\DonorDonationData as DonationDataFilter;
use Solidarity\Donor\Filter\DonorProfileData as ProfileDataFilter;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Donor\Service\Donor as DonorService;
use Solidarity\Donor\Service\NoNeedsException;
use Solidarity\Donor\Validator\DonorDonationData as DonationDataValidator;
use Solidarity\Donor\Validator\DonorProfileData as ProfileDataValidator;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Period\Entity\Period;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Repository\ProjectRepository;
use Solidarity\Transaction\Repository\TransactionRepository;
use Solidarity\Transaction\Service\Project as ProjectService;
use Solidarity\Transaction\Service\QrCode;
use Solidarity\Transaction\Service\Transaction as TransactionService;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;

/**
 * The one-time ("jednokratna donacija") path and, above all, the message it fails with.
 *
 * createTransaction() answers a donor pressing a button, so a zero allocation has to say
 * which condition blocked it. The rungs deliberately pass a null donor for the project and
 * payment-type questions: with the donor attached, those also answer "is this donor
 * eligible" and report the wrong cause — which is exactly the bug these tests exist to
 * prevent regressing.
 */
#[CoversClass(DonorService::class)]
final class CreateInstructionLadderTest extends IntegrationTestCase
{
    private const BANK = 1;
    private const WIRE = 2;

    public function testAllocatesAndReturnsTheTotalWhenEverythingMatches(): void
    {
        $project = $this->createProjectWithId(1, 'MSPR');
        $period = $this->processingPeriod($project);
        $donor = $this->donorPledgedTo($project);
        $this->needyBeneficiary($project, $period, self::BANK);

        self::assertSame(5000, $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000)));
    }

    public function testSaysThereAreNoNeedsAtAllWhenNothingIsProcessing(): void
    {
        $project = $this->createProjectWithId(1, 'MSPR');
        // Period exists but is not processing, so nothing anywhere is open.
        $period = $this->createPeriod($project);
        $project->periods->add($period);
        $donor = $this->donorPledgedTo($project);
        $this->needyBeneficiary($project, $period, self::BANK);

        $this->expectException(NoNeedsException::class);
        $this->expectExceptionMessage('Trenutno ne postoje potrebe.');

        $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000));
    }

    public function testBlamesTheProjectWhenTheNeedIsInADifferentOne(): void
    {
        $chosen = $this->createProjectWithId(1, 'MSPR');
        $this->processingPeriod($chosen); // open, but nobody registered to it

        $other = $this->createProjectWithId(2, 'MSP');
        $otherPeriod = $this->processingPeriod($other, month: 2);
        $this->needyBeneficiary($other, $otherPeriod, self::BANK);

        $donor = $this->donorPledgedTo($chosen);

        $this->expectException(NoNeedsException::class);
        $this->expectExceptionMessage('za izabrani pravac podrške');

        $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000));
    }

    public function testBlamesThePaymentMethodWhenNoBeneficiaryAcceptsIt(): void
    {
        $project = $this->createProjectWithId(1, 'MSPR');
        $period = $this->processingPeriod($project);
        // Open need in the chosen project, but payable only by wire.
        $this->needyBeneficiary($project, $period, self::WIRE);

        $donor = $this->donorPledgedTo($project);

        $this->expectException(NoNeedsException::class);
        $this->expectExceptionMessage('izabranim načinom plaćanja');

        $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000));
    }

    public function testBlamesTheDonorProfileWhenOnlyTheirOwnLimitsBlockIt(): void
    {
        // Everything matches for anyone else; this donor has used their whole per-person
        // allowance with the only beneficiary on offer. Reporting "no needs for this
        // direction" here would be a lie — the needs are there, this donor cannot serve them.
        $project = $this->createProjectWithId(1, 'MSPR');
        $period = $this->processingPeriod($project);
        $beneficiary = $this->needyBeneficiary($project, $period, self::BANK);

        $donor = $this->donorPledgedTo($project);
        $this->createTransaction($donor, $beneficiary, $project, $period, 30000, Transaction::STATUS_CONFIRMED);

        $this->expectException(NoNeedsException::class);
        $this->expectExceptionMessage('godišnji limit');

        $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000));
    }

    public function testBlamesTheAmountWhenEveryGatePassesButTheSliceIsTooSmall(): void
    {
        // The last rung, reachable now that allocateToBeneficiary floors the final amount:
        // needs exist, the project matches, the payment type matches, and the donor is
        // eligible — 200 RSD of per-person headroom is simply too little to instruct on.
        $project = $this->createProjectWithId(1, 'MSPR');
        $period = $this->processingPeriod($project);
        $beneficiary = $this->needyBeneficiary($project, $period, self::BANK);

        $donor = $this->donorPledgedTo($project);
        $this->createTransaction($donor, $beneficiary, $project, $period, 29800, Transaction::STATUS_CONFIRMED);

        $this->expectException(NoNeedsException::class);
        $this->expectExceptionMessage('Uneti iznos je premali');

        $this->service()->createTransaction($this->submission($donor, 1, self::BANK, 5000));
    }

    public function testRejectsAnUnknownDonor(): void
    {
        $this->createProjectWithId(1, 'MSPR');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Donor not found');

        $this->service()->createTransaction([
            'donorId' => 999999,
            'project' => 1,
            'payment' => [self::BANK => ['value' => 5000, 'currency' => 1]],
        ]);
    }

    // ---- helpers ------------------------------------------------------------

    /** The shape UpdateDonationData/CreateInstruction hand to the service, pre-filter. */
    private function submission(object $donor, int $projectId, int $type, int $amount): array
    {
        return [
            'donorId' => $donor->getId(),
            'project' => $projectId,
            'payment' => [$type => ['value' => $amount, 'currency' => $type === self::BANK ? 1 : 2]],
        ];
    }

    private function processingPeriod(Project $project, int $month = 1): Period
    {
        $period = $this->createPeriod($project, month: $month);
        $period->processing = true;
        $this->em()->flush();
        // Persisting a Period never populates the inverse side of an already-constructed
        // Project, and the allocator walks $project->periods.
        $project->periods->add($period);

        return $period;
    }

    private function donorPledgedTo(Project $project)
    {
        $donor = $this->createDonor();
        // The one-time path takes its budget from the submitted form, not from a stored
        // pledge, but the donor still has to be resolvable and linked to the project.
        $this->linkDonorToProject($donor, $project);

        return $donor;
    }

    private function needyBeneficiary(Project $project, Period $period, int $type)
    {
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod(
            $beneficiary,
            type: $type,
            accountNumber: $type === self::BANK ? '000999999999999180' : null,
            wireInstructions: $type === self::BANK ? null : 'SWIFT TESTRS22',
        );
        $this->createRegisteredPeriod($beneficiary, $project, $period, 240000);

        return $beneficiary;
    }

    private function service(): DonorService
    {
        $em = $this->em();

        $transactionValidator = new TransactionValidator(
            new CsrfTrueStub(),
            new DonorRepository($em),
            new BeneficiaryRepository($em),
        );
        $transactionFilter = new TransactionFilter($transactionValidator);

        // Real collaborators wherever the ladder's answer depends on them; stubs only for
        // the parts createTransaction() never reaches.
        return new DonorService(
            new DonorRepository($em),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(DonorFilter::class),
            $this->createStub(Mailer::class),
            new ProjectService(
                new ProjectRepository($em),
                $this->createStub(Session::class),
                new NullLogger(),
                $transactionFilter,
                $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
            ),
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
            $this->createStub(QrCode::class),
            $this->createStub(Translator::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
