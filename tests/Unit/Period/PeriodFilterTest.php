<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Period;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Period\Filter\Period as PeriodFilter;
use Skeletor\Core\Security\Csrf;

#[CoversClass(PeriodFilter::class)]
final class PeriodFilterTest extends TestCase
{
    public function testFilterSanitizesAndStripsCsrfToken(): void
    {
        $filter = new PeriodFilter();

        $result = $filter->filter([
            'id' => '7',
            'month' => 3,
            'year' => 2026,
            'type' => 'full',
            'active' => 1,
            'project' => 2,
            'processing' => 0,
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertSame(7, $result['id']);
        self::assertSame(3, $result['month']);
        self::assertSame('full', $result['type']);
        self::assertArrayNotHasKey(Csrf::TOKEN_NAME, $result);
    }

    public function testIdIsNullWhenMissing(): void
    {
        $filter = new PeriodFilter();

        $result = $filter->filter([
            'month' => 3,
            'year' => 2026,
            'type' => 'full',
            'active' => 1,
            'project' => 2,
            'processing' => 0,
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertNull($result['id']);
    }

    public function testGetErrorsIsEmpty(): void
    {
        self::assertSame([], (new PeriodFilter())->getErrors());
    }
}
