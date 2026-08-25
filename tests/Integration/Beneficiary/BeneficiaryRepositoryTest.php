<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;

#[CoversClass(BeneficiaryRepository::class)]
final class BeneficiaryRepositoryTest extends IntegrationTestCase
{
    private function repo(): BeneficiaryRepository
    {
        return new BeneficiaryRepository($this->em());
    }

    // ---- fetchByPeriod ---------------------------------------------------

    public function testFetchByPeriodReturnsOnlyBeneficiariesRegisteredForThatPeriod(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $otherPeriod = $this->createPeriod($project, month: 9);

        $registered = $this->createBeneficiary('Registered');
        $this->createRegisteredPeriod($registered, $project, $period, 10000);

        $elsewhere = $this->createBeneficiary('Elsewhere');
        $this->createRegisteredPeriod($elsewhere, $project, $otherPeriod, 10000);

        $result = $this->repo()->fetchByPeriod($period->getId());

        self::assertCount(1, $result);
        self::assertSame($registered->getId(), $result[0]->getId());
    }

    public function testFetchByPeriodExcludesNonNewBeneficiaries(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);

        $active = $this->createBeneficiary('Active', status: Beneficiary::STATUS_NEW);
        $this->createRegisteredPeriod($active, $project, $period, 10000);

        $deleted = $this->createBeneficiary('Deleted', status: Beneficiary::STATUS_DELETED);
        $this->createRegisteredPeriod($deleted, $project, $period, 10000);

        $result = $this->repo()->fetchByPeriod($period->getId());

        self::assertCount(1, $result);
        self::assertSame($active->getId(), $result[0]->getId());
    }

    public function testFetchByPeriodOrdersByLeastReceivedFirst(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();

        $funded = $this->createBeneficiary('Funded');
        $this->createRegisteredPeriod($funded, $project, $period, 10000);
        // Confirmed money already received -> should be ordered last.
        $this->createTransaction($donor, $funded, $project, $period, 5000, Transaction::STATUS_CONFIRMED);

        $unfunded = $this->createBeneficiary('Unfunded');
        $this->createRegisteredPeriod($unfunded, $project, $period, 10000);

        $result = $this->repo()->fetchByPeriod($period->getId());

        self::assertCount(2, $result);
        self::assertSame($unfunded->getId(), $result[0]->getId());
        self::assertSame($funded->getId(), $result[1]->getId());
    }

    // ---- assignOrphanedBeneficiariesToDelegate ---------------------------

    public function testAssignsOnlyOrphanedBeneficiariesInTheGivenSchool(): void
    {
        $delegate = $this->createDelegate();
        $otherDelegate = $this->createDelegate();
        $city = $this->createCity();
        $school = $this->createSchool($city);
        $otherSchool = $this->createSchool($city, name: 'Other School');

        $orphan = $this->createBeneficiary('Orphan', school: $school);
        $alreadyAssigned = $this->createBeneficiary('Assigned', school: $school, createdBy: $otherDelegate);
        $orphanElsewhere = $this->createBeneficiary('Elsewhere', school: $otherSchool);

        [$orphanId, $assignedId, $elsewhereId] = [$orphan->getId(), $alreadyAssigned->getId(), $orphanElsewhere->getId()];

        $this->repo()->assignOrphanedBeneficiariesToDelegate($school->getId(), $delegate->getId());
        $this->em()->clear();

        self::assertSame($delegate->getId(), $this->em()->find(Beneficiary::class, $orphanId)->createdBy->getId());
        self::assertSame($otherDelegate->getId(), $this->em()->find(Beneficiary::class, $assignedId)->createdBy->getId());
        self::assertNull($this->em()->find(Beneficiary::class, $elsewhereId)->createdBy);
    }

    // ---- nullifyCreatedByForDelegate -------------------------------------

    public function testNullifiesOnlyBeneficiariesOfTheGivenDelegate(): void
    {
        $delegate = $this->createDelegate();
        $otherDelegate = $this->createDelegate();

        $mine = $this->createBeneficiary('Mine', createdBy: $delegate);
        $theirs = $this->createBeneficiary('Theirs', createdBy: $otherDelegate);

        [$mineId, $theirsId] = [$mine->getId(), $theirs->getId()];

        $this->repo()->nullifyCreatedByForDelegate($delegate->getId());
        $this->em()->clear();

        self::assertNull($this->em()->find(Beneficiary::class, $mineId)->createdBy);
        self::assertSame($otherDelegate->getId(), $this->em()->find(Beneficiary::class, $theirsId)->createdBy->getId());
    }
}
