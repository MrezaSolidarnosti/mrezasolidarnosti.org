<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\User;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Tests\Support\AssertsFieldCoverage;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\User\Entity\User;
use Solidarity\User\Factory\UserFactory;
use Solidarity\User\Filter\User as UserFilter;
use Solidarity\User\Validator\User as UserValidator;
use Skeletor\Core\Security\Csrf;

/**
 * Posted staff-user data, through the filter and factory, into the database.
 *
 * See PeriodFactoryTest for why the trip starts at $postData.
 */
#[CoversClass(UserFactory::class)]
#[CoversClass(UserFilter::class)]
final class UserFactoryTest extends IntegrationTestCase
{
    use AssertsFieldCoverage;

    /** Fields the user form posts, and what this test posts for each. */
    private const WRITABLE = [
        'firstName' => 'Mila',
        'lastName' => 'Jovanov',
        'email' => 'mila@example.com',
        'role' => User::ROLE_ADMIN,
        'isActive' => 1,
        'displayName' => 'Mila J',
    ];

    /** Fields the user form does not post, with the reason. */
    private const IGNORED = [
        'ipv4' => 'stamped by updateLoginInfo() when the person signs in',
        'lastLogin' => 'stamped by updateLoginInfo() when the person signs in',
    ];

    public function testEveryMappedFieldIsAccountedFor(): void
    {
        // Note what is absent: `password`. This app authenticates by magic link and the
        // property is deliberately unmapped, so it is not a persisted field at all.
        $this->assertEveryMappedFieldIsAccountedFor(User::class, self::WRITABLE, self::IGNORED);
    }

    public function testAPostedUserIsStoredWithEveryFieldItCarried(): void
    {
        $id = UserFactory::compileEntityForCreate($this->filter(), $this->em());

        $this->em()->clear();
        $stored = $this->em()->find(User::class, $id);

        foreach (self::WRITABLE as $field => $expected) {
            self::assertSame($expected, $stored->$field, $field . ' did not survive the round trip');
        }
    }

    public function testABlankDisplayNameFallsBackToTheFullName(): void
    {
        // The name shown throughout the dashboard. Left blank it has to become something,
        // or the person appears as an empty string in every list that renders them.
        $id = UserFactory::compileEntityForCreate($this->filter(['displayName' => '']), $this->em());

        $this->em()->clear();

        self::assertSame('Mila Jovanov', $this->em()->find(User::class, $id)->displayName);
    }

    public function testAnUpdateChangesTheStoredUserRatherThanAddingAnother(): void
    {
        // compileEntityForUpdate loads by id and mutates; a factory that built a fresh
        // entity here would quietly duplicate every edited user.
        $id = UserFactory::compileEntityForCreate($this->filter(), $this->em());

        UserFactory::compileEntityForUpdate(
            $this->filter(['id' => $id, 'firstName' => 'Milos', 'displayName' => 'Milos J']),
            $this->em(),
        );
        $this->em()->flush();
        $this->em()->clear();

        self::assertCount(1, $this->em()->getRepository(User::class)->findAll());
        self::assertSame('Milos', $this->em()->find(User::class, $id)->firstName);
    }

    /**
     * Runs $postData through the real filter.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function filter(array $overrides = []): array
    {
        $filter = new UserFilter(new UserValidator($this->em(), new CsrfTrueStub()));

        // 'delegate' is posted by the form and consumed by the filter, but User has no
        // delegate field — the factory drops it. It is required here because the filter
        // reads the key without a default.
        return $filter->filter($overrides + self::WRITABLE + [
            'id' => null,
            'delegate' => null,
            Csrf::TOKEN_NAME => 'token',
        ]);
    }
}
