<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Period;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Period\Entity\Period;
use Solidarity\Transaction\Entity\Project;

#[CoversClass(Period::class)]
final class PeriodTest extends TestCase
{
    public function testGetLabelCombinesProjectCodeMonthYearAndType(): void
    {
        $project = new Project();
        $project->code = 'PRJ';

        $period = new Period();
        $period->project = $project;
        $period->month = 3;
        $period->year = 2026;
        $period->type = Period::TYPE_FULL;

        self::assertSame('PRJ-3-2026-full', $period->getLabel());
    }
}
