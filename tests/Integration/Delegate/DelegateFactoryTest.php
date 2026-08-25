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

        // The school's owning side was pointed at the new delegate.
        $school = $this->em()->find(School::class, $schoolId);
        self::assertSame($id, $school->delegate->getId());
    }
}
