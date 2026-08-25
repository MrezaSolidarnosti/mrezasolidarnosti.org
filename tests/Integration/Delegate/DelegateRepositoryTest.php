<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(DelegateRepository::class)]
final class DelegateRepositoryTest extends IntegrationTestCase
{
    public function testGetAffectedDelegatesReturnsOnlyDelegatesWhoseBeneficiariesHaveTransactions(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $city = $this->createCity();

        // Affected: delegate -> school -> beneficiary -> transaction
        $affected = $this->createDelegate();
        $affectedSchool = $this->createSchool($city, name: 'Affected School');
        $affectedSchool->delegate = $affected;
        $this->em()->flush();
        $beneficiary = $this->createBeneficiary(school: $affectedSchool);
        $this->createTransaction($donor, $beneficiary, $project, $period, 5000);

        // Unaffected: delegate with a school but no transacted beneficiaries
        $unaffected = $this->createDelegate();
        $emptySchool = $this->createSchool($city, name: 'Empty School');
        $emptySchool->delegate = $unaffected;
        $this->em()->flush();

        $rows = (new DelegateRepository($this->em()))->getAffectedDelegates();
        $ids = array_map('intval', array_column($rows, 'id'));

        self::assertContains($affected->getId(), $ids);
        self::assertNotContains($unaffected->getId(), $ids);
    }
}
