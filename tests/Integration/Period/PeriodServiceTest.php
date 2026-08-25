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

    private function service(): PeriodService
    {
        return new PeriodService(
            new PeriodRepository($this->em()),
            $this->createStub(Session::class),
            new NullLogger(),
            $this->createStub(\Skeletor\Core\Activity\Service\Activity::class),
        );
    }
}
