<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Core;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A tripwire, not a description.
 *
 * Every persisted field of every entity is listed below. Add a column and this fails until
 * you come back here — which is the point: the list is a checkpoint, not documentation. A
 * new field usually needs more than a migration, and the things it needs are all in
 * different files:
 *
 *   - the **Filter** has to pass it through (they build their output array by hand, so an
 *     unlisted key is silently dropped — that is how a period's `maxAmount` was posted and
 *     discarded on every save)
 *   - the **Validator** may need a rule
 *   - the **Factory** may need to resolve it (relations are resolved by id)
 *   - `prepareEntities()` has to emit it if it belongs in a table, and
 *     `compileTableColumns()` has to declare it
 *   - a **nullable** relation means every reader needs a guard, or the column should not be
 *     nullable (a typeless school used to fatal the whole school list)
 *
 * Update the list, then check that trail. If the field is deliberately internal, say so in a
 * comment next to it.
 */
final class EntityShapeTest extends TestCase
{
    /**
     * Entity => every mapped property, sorted.
     *
     * `id`, `createdAt` and `updatedAt` come from the Timestampable trait and are excluded
     * below, so they never appear here.
     *
     * @return array<string, array{class-string, string[]}>
     */
    public static function entities(): array
    {
        return [
            'Beneficiary' => [\Solidarity\Beneficiary\Entity\Beneficiary::class, [
                'comment', 'createdBy', 'name', 'paymentMethods', 'registeredPeriods', 'school',
                'status', 'transactions',
            ]],
            'Beneficiary payment method' => [\Solidarity\Beneficiary\Entity\PaymentMethod::class, [
                'accountNumber', 'beneficiary', 'type', 'wireInstructions',
            ]],
            'Registered period' => [\Solidarity\Beneficiary\Entity\RegisteredPeriods::class, [
                'amount', 'beneficiary', 'period', 'project',
            ]],
            'Delegate' => [\Solidarity\Delegate\Entity\Delegate::class, [
                'adminComment', 'comment', 'email', 'ipv4', 'lastLogin', 'name',
                'phone', 'projects', 'schools', 'status', 'verifiedBy',
            ]],
            'Delegate request' => [\Solidarity\Delegate\Entity\UserDelegateRequest::class, [
                'adminComment', 'comment', 'firstName', 'lastName', 'phone', 'school', 'status',
                'totalBlockedEducators', 'totalEducators',
            ]],
            'Donor' => [\Solidarity\Donor\Entity\Donor::class, [
                'comment', 'email', 'firstName', 'ipv4', 'isActive', 'lastLogin', 'lastName',
                'lastVisit', 'paymentMethods', 'projects', 'status', 'transactions', 'wantsToDonateTo',
            ]],
            'Donor payment method' => [\Solidarity\Donor\Entity\PaymentMethod::class, [
                'amount', 'currency', 'donor', 'monthly', 'project', 'type',
            ]],
            'Email list' => [\Solidarity\EmailList\Entity\EmailList::class, [
                'email', 'isActive',
            ]],
            // seoTitle/seoDescription/seoImage come from the Skeletor Seo trait; reflection
            // reports trait properties as the class's own, so they belong here.
            'Page' => [\Solidarity\Page\Entity\Page::class, [
                'blockData', 'description', 'featuredImage', 'isLoginProtected', 'languageCode',
                'seoDescription', 'seoImage', 'seoTitle', 'slug', 'status', 'title',
                'translationGroupId',
            ]],
            'Period' => [\Solidarity\Period\Entity\Period::class, [
                'active', 'beneficiaries', 'maxAmount', 'month', 'processing', 'project', 'type', 'year',
            ]],
            // City::$schools exists but its mapping is commented out, so it is not persisted.
            'City' => [\Solidarity\School\Entity\City::class, [
                'name',
            ]],
            'School' => [\Solidarity\School\Entity\School::class, [
                'beneficiaries', 'city', 'delegate', 'havePayoutPriority', 'name', 'processing', 'type',
            ]],
            'School type' => [\Solidarity\School\Entity\SchoolType::class, [
                'name', 'schools',
            ]],
            'Project' => [\Solidarity\Transaction\Entity\Project::class, [
                'code', 'delegates', 'logo', 'name', 'periods',
            ]],
            'Transaction' => [\Solidarity\Transaction\Entity\Transaction::class, [
                'accountNumber', 'amount', 'amountEur', 'beneficiary', 'comment', 'donor',
                'instructions', 'manual', 'paymentCode', 'paymentType', 'period', 'project', 'status',
            ]],
            // No 'password': this app authenticates by magic link and the property is
            // declared unmapped, only to keep setPassword() off a dynamic property.
            'User' => [\Solidarity\User\Entity\User::class, [
                'displayName', 'email', 'firstName', 'ipv4', 'isActive', 'lastLogin', 'lastName', 'role',
            ]],
        ];
    }

    /**
     * @param class-string $class
     * @param string[]     $expected
     */
    #[DataProvider('entities')]
    public function testTheEntityHasExactlyTheFieldsWeExpect(string $class, array $expected): void
    {
        sort($expected);

        self::assertSame(
            $expected,
            self::mappedProperties($class),
            $class . ": the persisted fields have changed. Update the list in this test, then check"
                . " the filter, validator, factory and table columns for the same package —"
                . " see this class's docblock for why.",
        );
    }

    public function testEveryCollectionIsInitialisedSoTheEntityIsUsableWithoutDoctrine(): void
    {
        // Doctrine bypasses the constructor when it hydrates, so this only matters app-side —
        // but factories and fixtures do `new Entity()`, and an uninitialised Collection is a
        // fatal the first time anything iterates it.
        //
        // Looped rather than data-provided: most entities hold no collection at all, and a
        // per-entity case would report those as risky tests that assert nothing.
        $checked = 0;

        foreach (self::entities() as [$class]) {
            $entity = new $class();

            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                if (($property->getType()?->getName() ?? '') !== Collection::class) {
                    continue;
                }
                self::assertTrue(
                    $property->isInitialized($entity),
                    sprintf('%s::$%s is a Collection and must be initialised in the constructor', $class, $property->getName()),
                );
                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, 'no Collection properties found — has the mapping style changed?');
    }

    /**
     * Property names Doctrine persists, sorted, minus what Timestampable contributes.
     *
     * Read from the attributes rather than a metadata factory so this stays a unit test —
     * it needs no database, and it is the mapping itself that is under scrutiny.
     *
     * @param class-string $class
     * @return string[]
     */
    private static function mappedProperties(string $class): array
    {
        $mappings = [ORM\Column::class, ORM\ManyToOne::class, ORM\OneToMany::class, ORM\ManyToMany::class, ORM\OneToOne::class];
        $inherited = ['id', 'createdAt', 'updatedAt'];

        $fields = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (in_array($property->getName(), $inherited, true)) {
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
