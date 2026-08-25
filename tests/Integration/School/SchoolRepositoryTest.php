<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\School;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\School\Repository\SchoolRepository;
use Solidarity\Tests\Integration\IntegrationTestCase;

#[CoversClass(SchoolRepository::class)]
final class SchoolRepositoryTest extends IntegrationTestCase
{
    private function repo(): SchoolRepository
    {
        return new SchoolRepository($this->em());
    }

    public function testGetByNameAndCityReturnsMatchingSchool(): void
    {
        $city = $this->createCity('Beograd');
        $school = $this->createSchool($city, name: 'Gimnazija');

        $found = $this->repo()->getByNameAndCity('Gimnazija', 'Beograd');

        self::assertNotFalse($found);
        self::assertSame($school->getId(), $found->getId());
    }

    public function testGetByNameAndCityReturnsFalseWhenNoMatch(): void
    {
        $city = $this->createCity('Beograd');
        $this->createSchool($city, name: 'Gimnazija');

        self::assertFalse($this->repo()->getByNameAndCity('Nepostojeca', 'Beograd'));
        self::assertFalse($this->repo()->getByNameAndCity('Gimnazija', 'Novi Sad'));
    }
}
