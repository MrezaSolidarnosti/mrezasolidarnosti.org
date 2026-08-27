<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Period;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Skeletor\User\Service\Session;
use Solidarity\Period\Entity\Period as PeriodEntity;
use Solidarity\Period\Repository\PeriodRepository;
use Solidarity\Period\Service\Period as PeriodService;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Service\Project as ProjectService;

/**
 * Periods are rounds, and two flags decide what happens in one.
 *
 * `active` is an editing flag: it controls whether a period is offered in the dropdowns
 * where beneficiaries and transactions are created. `processing` is an operational one:
 * `fetchProcessing()` is what the allocation cron iterates, so a period that is not
 * processing receives no money no matter how many people are registered for it. They are
 * independent, and the two tests below pin that — a round is normally processing *after* it
 * has stopped being active for editing.
 */
#[CoversClass(PeriodService::class)]
#[CoversClass(PeriodRepository::class)]
final class PeriodServiceTest extends IntegrationTestCase
{
    /** Supplies the project column's filter options; one test swaps in its own. */
    private ?ProjectService $projectService = null;

    // ---- what the cron picks up --------------------------------------------------------

    public function testOnlyProcessingPeriodsAreHandedToTheAllocator(): void
    {
        $project = $this->createProject();
        $open = $this->createPeriod($project, month: 3);
        $closed = $this->createPeriod($project, month: 4);
        $open->processing = true;
        $this->em()->flush();

        $processing = (new PeriodRepository($this->em()))->fetchProcessing();

        self::assertSame([$open->getId()], array_map(static fn ($p) => $p->getId(), $processing));
    }

    public function testAPeriodCanBeProcessingWithoutStillBeingActive(): void
    {
        // The normal shape of a round in flight: registration is closed (not active, so it
        // is off the dropdowns) while the cron is still allocating against it. Tying the two
        // together would either reopen registration or stop the payouts.
        $project = $this->createProject();
        $period = $this->createPeriod($project);
        $period->active = false;
        $period->processing = true;
        $this->em()->flush();

        self::assertCount(1, (new PeriodRepository($this->em()))->fetchProcessing());
        self::assertSame([], $this->service()->getFilterData());
    }

    // ---- the dropdown -------------------------------------------------------------------

    public function testTheDropdownOffersOnlyActivePeriods(): void
    {
        $project = $this->createProject();
        $offered = $this->createPeriod($project, month: 3);
        $retired = $this->createPeriod($project, month: 4);
        $retired->active = false;
        $this->em()->flush();

        $options = $this->service()->getFilterData();

        self::assertSame([$offered->getId()], array_keys($options));
    }

    public function testEachOptionIsLabelledTheWayAPersonReadsARound(): void
    {
        $period = $this->createPeriod($this->createProject(), month: 3, year: 2026);

        $options = $this->service()->getFilterData();

        self::assertSame($period->getLabel(), $options[$period->getId()]);
        self::assertNotSame('', $options[$period->getId()]);
    }

    // ---- the table ----------------------------------------------------------------------

    public function testARowCarriesTheProjectCodeAndBothFlags(): void
    {
        // The project code matters: month and year alone do not identify a round when two
        // projects run concurrently, which is the normal state.
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project, month: 5, year: 2026);
        $period->processing = true;
        $this->em()->flush();
        $id = $period->getId();

        // createdAt is insertable:false, so the row builder needs a re-read entity.
        $this->em()->clear();
        $row = $this->service()->prepareEntities([$this->em()->find(PeriodEntity::class, $id)])[0];

        self::assertSame($id, $row['id']);
        self::assertSame('MSPR', $row['columns']['project']);
        // The table shows the month by name; the number is an implementation detail nobody
        // reading the period list should have to translate in their head.
        self::assertSame('Maj', $row['columns']['month']);
        self::assertSame(2026, $row['columns']['year']);
        self::assertTrue($row['columns']['active']);
        self::assertTrue($row['columns']['processing']);
    }

    public function testAMonthOutsideTheCalendarIsShownAsItsNumberRatherThanBlank(): void
    {
        // A blank cell would read as "no month" and hide the bad data; the number at least
        // says what is actually stored.
        $period = $this->createPeriod($this->createProject(), month: 13);
        $id = $period->getId();
        $this->em()->clear();

        $row = $this->service()->prepareEntities([$this->em()->find(PeriodEntity::class, $id)])[0];

        self::assertSame('13', $row['columns']['month']);
    }

    public function testEveryDeclaredColumnIsProducedByTheRowBuilder(): void
    {
        $period = $this->createPeriod($this->createProject());
        $id = $period->getId();
        $this->em()->clear();

        $service = $this->service();
        $row = $service->prepareEntities([$this->em()->find(PeriodEntity::class, $id)])[0]['columns'];

        foreach ($service->compileTableColumns() as $column) {
            self::assertArrayHasKey($column['name'], $row, $column['name']);
        }
    }

    public function testTheProjectColumnOffersTheProjectsAsFilterOptions(): void
    {
        // Two projects run concurrent rounds, so an unfiltered period list interleaves them.
        $this->projectService = $this->createStub(ProjectService::class);
        $this->projectService->method('getFilterData')->willReturn([1 => 'MSP - Mreza', 2 => 'MSPR - Represija']);

        $columns = array_column($this->service()->compileTableColumns(), null, 'name');

        self::assertSame([1 => 'MSP - Mreza', 2 => 'MSPR - Represija'], $columns['project']['filterData']);
    }

    // ---- saving a period ----------------------------------------------------------------

    public function testAMonthChosenFromTheDropdownIsStoredAsAnInteger(): void
    {
        // The select posts the month number as a string; the column is INTEGER.
        $period = $this->createdWith(['month' => '11']);

        self::assertSame(11, $period->month);
    }


    public function testABlankMaximumIsSavedAsZeroRatherThanFailing(): void
    {
        // The reported 500. maxAmount is a non-nullable int and the form posts '' when the
        // field is left empty; PHP will coerce '5000' to an int but not '', so the assignment
        // in AbstractFactory threw. Zero is already how "no per-period override" is spelled.
        $period = $this->createdWith(['maxAmount' => '']);

        self::assertSame(0, $period->maxAmount);
    }

    public function testAMaximumTypedIntoTheFormIsStoredAsAnInteger(): void
    {
        $period = $this->createdWith(['maxAmount' => '50000']);

        self::assertSame(50000, $period->maxAmount);
    }

    public function testABlankMonthLandsAsZeroRatherThanBreakingTheInsert(): void
    {
        // month reads as ?int on the property but its #[ORM\Column] carries no
        // `nullable: true`, so the column is NOT NULL and writing null is an integrity
        // violation, not a saved period. The select is required, so this is the
        // belt-and-braces path; 0 renders as a literal "0" in the table via getHrMonth(),
        // which is visible rather than fatal.
        $period = $this->createdWith(['month' => '']);

        self::assertSame(0, $period->month);
    }

    public function testTheFlagsArriveAsBooleansNotStrings(): void
    {
        $period = $this->createdWith(['active' => '1', 'processing' => '0']);

        self::assertTrue($period->active);
        self::assertFalse($period->processing);
    }

    /**
     * Creates a period through the service the way the form posts it — strings throughout,
     * which is the point: the filter is what turns them into the entity's declared types.
     *
     * @param array<string, mixed> $overrides
     */
    private function createdWith(array $overrides): PeriodEntity
    {
        $project = $this->createProject('MSPR');

        $this->service()->create($overrides + [
            'month' => '8',
            'year' => '2026',
            'type' => PeriodEntity::TYPE_FULL,
            'active' => '1',
            'processing' => '0',
            'project' => (string) $project->getId(),
            'maxAmount' => '240000',
        ]);

        $this->em()->clear();
        $periods = (new PeriodRepository($this->em()))->fetchAll([]);

        return $periods[array_key_last($periods)];
    }

    private function service(): PeriodService
    {
        // The real filter, not a stub: it is what casts the posted strings to the types the
        // entity declares, and wiring it is the whole point — CrudService skips filtering
        // entirely when the slot is empty, which is how a blank "Max iznos" reached the
        // factory as '' and became a TypeError.
        return new PeriodService(
            new PeriodRepository($this->em()),
            $this->createStub(Session::class),
            new NullLogger(),
            new \Solidarity\Period\Filter\Period(),
            // Stubbed: it only supplies the project column's filter options, and these tests
            // are about what the service saves and renders, not about that list's contents.
            $this->projectService ?? $this->createStub(ProjectService::class),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
