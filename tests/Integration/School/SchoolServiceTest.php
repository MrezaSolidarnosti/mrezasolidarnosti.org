<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\School;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\School\Entity\School as SchoolEntity;
use Solidarity\School\Repository\SchoolRepository;
use Solidarity\School\Service\School as SchoolService;
use Solidarity\Tests\Integration\IntegrationTestCase;

/**
 * The school dropdown and the school table.
 *
 * getFilterData() is what fills the school `<select>` on the beneficiary form — the field
 * that decides which delegate ends up owning a beneficiary, since
 * `Beneficiary\Filter` resolves `createdBy` from the chosen school's delegate. The label it
 * builds carries the city because school names repeat across towns, and picking the wrong
 * "Osnovna škola Vuk Karadžić" assigns the person to a delegate in another city.
 */
#[CoversClass(SchoolService::class)]
final class SchoolServiceTest extends IntegrationTestCase
{
    public function testASchoolCannotExistWithoutAType(): void
    {
        // Enforced by the mapping now rather than by every reader remembering to guard:
        // one typeless row used to fatal the whole /school/view/ table.
        $property = new \ReflectionProperty(SchoolEntity::class, 'type');

        self::assertFalse($property->getType()->allowsNull());
    }

    public function testTheDropdownLabelsEachSchoolWithItsCity(): void
    {
        // Without the city these are indistinguishable in the list, and the choice decides
        // which delegate the beneficiary is assigned to.
        $belgrade = $this->createCity('Beograd');
        $school = $this->createSchool($belgrade, null, 'Osnovna škola Vuk Karadžić');

        $options = $this->service()->getFilterData();

        self::assertSame('Osnovna škola Vuk Karadžić Beograd', $options[$school->getId()]);
    }

    public function testTheDropdownIsKeyedByIdSoTheFormPostsSomethingStable(): void
    {
        $first = $this->createSchool($this->createCity(), null, 'First School');
        $second = $this->createSchool($this->createCity(), null, 'Second School');

        $options = $this->service()->getFilterData();

        self::assertArrayHasKey($first->getId(), $options);
        self::assertArrayHasKey($second->getId(), $options);
    }

    public function testTheDropdownIsEmptyRatherThanBrokenBeforeAnySchoolExists(): void
    {
        self::assertSame([], $this->service()->getFilterData());
    }

    // ---- the table -------------------------------------------------------------------

    public function testEachRowCarriesTheNameTypeAndCity(): void
    {
        $city = $this->createCity('Novi Sad');
        $type = $this->createSchoolType(31, 'Gimnazija');
        $school = $this->createSchool($city, $type, 'Gimnazija Jovan Jovanović Zmaj');
        $id = $school->getId();

        // createdAt is insertable:false — the entity this test just persisted has none until
        // it is read back, and prepareEntities() formats it.
        $this->em()->clear();
        $row = $this->service()->prepareEntities([$this->em()->find(SchoolEntity::class, $id)])[0];

        self::assertSame($id, $row['id']);
        self::assertSame('Gimnazija Jovan Jovanović Zmaj', $row['columns']['name']['value']);
        self::assertSame('Gimnazija', $row['columns']['schoolType']);
        self::assertSame('Novi Sad', $row['columns']['city']);
    }

    public function testTheNameIsTheColumnYouEditFrom(): void
    {
        // The table turns this flag into the inline edit link; without it the row is a
        // dead end and the school can only be reached by guessing its URL.
        $school = $this->createSchool($this->createCity(), $this->createSchoolType(32), 'Editable');
        $id = $school->getId();
        $this->em()->clear();

        $row = $this->service()->prepareEntities([$this->em()->find(SchoolEntity::class, $id)])[0];

        self::assertTrue($row['columns']['name']['editColumn']);
    }

    public function testEveryDeclaredColumnIsProducedByTheRowBuilder(): void
    {
        // compileTableColumns() and prepareEntities() are written independently; a column
        // declared but never produced renders as an empty cell with no clue why.
        $school = $this->createSchool($this->createCity(), $this->createSchoolType(33), 'Complete');
        $id = $school->getId();
        $this->em()->clear();

        $service = $this->service();
        $row = $service->prepareEntities([$this->em()->find(SchoolEntity::class, $id)])[0]['columns'];

        foreach ($service->compileTableColumns() as $column) {
            self::assertArrayHasKey($column['name'], $row, $column['name']);
        }
    }

    private function service(): SchoolService
    {
        return new SchoolService(
            new SchoolRepository($this->em()),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
