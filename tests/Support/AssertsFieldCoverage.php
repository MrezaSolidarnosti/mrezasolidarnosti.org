<?php

declare(strict_types=1);

namespace Solidarity\Tests\Support;

use Doctrine\ORM\Mapping as ORM;

/**
 * The tripwire behind the *FactoryTest classes.
 *
 * Each of those declares WRITABLE (fields a posted form sets, with the value it posts) and
 * IGNORED (fields no form writes, with the reason). Together they have to account for every
 * persisted field, so adding a column fails the suite until somebody categorises it — and
 * categorising it means looking at the filter, which is where fields get silently dropped.
 *
 * `EntityShapeTest` catches the column appearing at all; this catches it never arriving.
 */
trait AssertsFieldCoverage
{
    /**
     * @param class-string          $entity
     * @param array<string, mixed>  $writable fields a form posts => the value posted
     * @param array<string, string> $ignored  fields no form posts => why not
     */
    protected function assertEveryMappedFieldIsAccountedFor(string $entity, array $writable, array $ignored): void
    {
        $accountedFor = array_merge(array_keys($writable), array_keys($ignored));
        sort($accountedFor);

        self::assertSame(
            $accountedFor,
            self::mappedFields($entity),
            $entity . ' has a persisted field this test says nothing about. Add it to WRITABLE'
                . ' or IGNORED, and check the Filter passes it through — filters build their'
                . ' output array by hand, so an unlisted key is dropped without an error.',
        );
    }

    /**
     * Persisted property names, sorted. Same rule as EntityShapeTest: read from the mapping
     * attributes, minus what Timestampable contributes.
     *
     * @param class-string $entity
     * @return string[]
     */
    protected static function mappedFields(string $entity): array
    {
        $mappings = [ORM\Column::class, ORM\ManyToOne::class, ORM\OneToMany::class, ORM\ManyToMany::class, ORM\OneToOne::class];
        $fields = [];

        foreach ((new \ReflectionClass($entity))->getProperties() as $property) {
            if (in_array($property->getName(), ['id', 'createdAt', 'updatedAt'], true)) {
                continue;
            }
            foreach ($mappings as $mapping) {
                if ($property->getAttributes($mapping) !== []) {
                    $fields[] = $property->getName();
                    break;
                }
            }
        }
        sort($fields);

        return $fields;
    }
}
