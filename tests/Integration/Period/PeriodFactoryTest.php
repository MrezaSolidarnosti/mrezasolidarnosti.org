<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Period;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Period\Entity\Period;
use Solidarity\Period\Factory\PeriodFactory;
use Solidarity\Period\Filter\Period as PeriodFilter;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Support\AssertsFieldCoverage;
use Skeletor\Core\Security\Csrf;

/**
 * A round trip from posted form data to stored row, and a tripwire on the fields it covers.
 *
 * This is the shape `EntityShapeTest` asks you to check when a column is added. That test
 * catches the column appearing; this one catches it being **silently dropped on the way in**
 * — which is what happened to a period's `maxAmount`: the input was on the form, the filter
 * built its output array by hand and never listed the key, so every save discarded it and
 * nothing failed.
 *
 * The trip starts at `$postData` rather than at the factory on purpose. Testing the factory
 * alone would have passed throughout that bug, because the factory never saw the field.
 *
 * Worth copying for the other entities: the tripwire is `testEveryMappedFieldIsAccountedFor`,
 * which forces every new field into either WRITABLE or IGNORED before the suite goes green.
 */
#[CoversClass(PeriodFactory::class)]
#[CoversClass(PeriodFilter::class)]
final class PeriodFactoryTest extends IntegrationTestCase
{
    use AssertsFieldCoverage;

    /** Fields a posted form can set, and the value this test posts for each. */
    private const WRITABLE = [
        'month' => 3,
        'year' => 2026,
        'type' => Period::TYPE_FULL,
        'active' => true,
        'processing' => true,
        'maxAmount' => 50000,
    ];

    /** Fields no form writes, with the reason. */
    private const IGNORED = [
        'project' => 'a relation, resolved from a posted id and asserted separately',
        'beneficiaries' => 'inverse side of RegisteredPeriods; written by the beneficiary form',
    ];

    public function testEveryMappedFieldIsAccountedFor(): void
    {
        // The tripwire. Add a column to Period and this fails until it is either given a
        // value in WRITABLE — which then has to survive the round trip below — or listed in
        // IGNORED with a reason. Either way somebody has looked at the filter.
        $this->assertEveryMappedFieldIsAccountedFor(Period::class, self::WRITABLE, self::IGNORED);
    }

    public function testAPostedPeriodIsStoredWithEveryFieldItCarried(): void
    {
        $project = $this->createProject('MSPR');

        $id = PeriodFactory::compileEntityForCreate(
            (new PeriodFilter())->filter(self::WRITABLE + [
                'id' => null,
                'project' => $project->getId(),
                Csrf::TOKEN_NAME => 'token',
            ]),
            $this->em(),
        );

        $this->em()->clear();
        $stored = $this->em()->find(Period::class, $id);

        self::assertSame($project->getId(), $stored->project->getId());
        foreach (self::WRITABLE as $field => $expected) {
            self::assertSame($expected, $stored->$field, $field . ' did not survive the round trip');
        }
    }

    public function testABlankMaximumIsStoredAsZeroMeaningNoOverride(): void
    {
        // Found by the tripwire above: the filter used to write null for a blank input, and
        // the column is NOT NULL — so leaving "Max iznos" empty on the period form was a 500
        // on save. 0 is the established spelling for "no per-period override": MigrateLegacy
        // writes it for every legacy period, and Beneficiary\Validator reads anything <= 0 as
        // "fall back to the global limit".
        $project = $this->createProject('MSPR');

        $id = PeriodFactory::compileEntityForCreate(
            // The override goes on the left: `+` keeps the left-hand key, so putting it
            // after WRITABLE would silently leave the 50000 in place.
            (new PeriodFilter())->filter([
                'id' => null,
                'project' => $project->getId(),
                'maxAmount' => '',
                Csrf::TOKEN_NAME => 'token',
            ] + self::WRITABLE),
            $this->em(),
        );

        $this->em()->clear();

        self::assertSame(0, $this->em()->find(Period::class, $id)->maxAmount);
    }

}
