<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Factory\DelegateFactory;
use Solidarity\School\Entity\School;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(DelegateFactory::class)]
final class DelegateFactoryTest extends IntegrationTestCase
{
    public function testCreatePersistsDelegateLinksProjectsAndAssignsSchools(): void
    {
        $project = $this->createProject('MSPR');
        $school = $this->createSchool($this->createCity());
        $schoolId = $school->getId();

        $id = DelegateFactory::compileEntityForCreate([
            'id' => null,
            'email' => 'delegate-factory@example.com',
            'name' => 'Test Delegate',
            'status' => Delegate::STATUS_VERIFIED,
            'phone' => '0601234567',
            'verifiedBy' => 'Admin',
            'comment' => null,
            'adminComment' => null,
            'projects' => [$project->getId()],
            'schools' => [$schoolId],
        ], $this->em());

        $this->em()->clear();
        $delegate = $this->em()->find(Delegate::class, $id);

        self::assertSame('delegate-factory@example.com', $delegate->email);
        self::assertSame(Delegate::STATUS_VERIFIED, $delegate->status);
        self::assertCount(1, $delegate->projects);

        // The join row was written from the delegate (owning) side.
        $school = $this->em()->find(School::class, $schoolId);
        self::assertTrue($school->hasDelegate($id));
    }

    public function testASchoolCanBeAssignedToASecondDelegateWithoutLeavingTheFirst(): void
    {
        // The old OneToMany write re-pointed school.delegate_id, silently taking the school
        // away from whoever held it. A shared school must keep both.
        $school = $this->createSchool($this->createCity());
        $schoolId = $school->getId();
        $first = $this->createDelegate();
        $this->assignSchool($first, $school);
        $firstId = $first->getId();

        $secondId = DelegateFactory::compileEntityForCreate([
            'id' => null,
            'email' => 'second-delegate@example.com',
            'name' => 'Second Delegate',
            'status' => Delegate::STATUS_VERIFIED,
            'phone' => '0601234567',
            'verifiedBy' => 'Admin',
            'comment' => null,
            'adminComment' => null,
            'projects' => [],
            'schools' => [$schoolId],
        ], $this->em());

        $this->em()->clear();
        $school = $this->em()->find(School::class, $schoolId);

        self::assertCount(2, $school->delegates);
        self::assertTrue($school->hasDelegate($firstId));
        self::assertTrue($school->hasDelegate($secondId));
    }
}
