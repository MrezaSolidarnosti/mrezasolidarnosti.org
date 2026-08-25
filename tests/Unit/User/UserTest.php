<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\User\Entity\User;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testGetHrRoleReturnsLabelForKnownRole(): void
    {
        self::assertSame('Admin', User::getHrRole(User::ROLE_ADMIN));
        self::assertSame('Saradnik', User::getHrRole(User::ROLE_STUFF));
    }

    public function testGetHrRolesContainsAdminAndStaff(): void
    {
        $roles = User::getHrRoles();

        self::assertArrayHasKey(User::ROLE_ADMIN, $roles);
        self::assertArrayHasKey(User::ROLE_STUFF, $roles);
    }

    public function testGetRoleCastsToInt(): void
    {
        $user = new User();
        $user->role = User::ROLE_ADMIN;

        self::assertSame(1, $user->getRole());
        self::assertSame(1, $user->getAuthRole());
    }

    public function testIsActiveReflectsStatusFlag(): void
    {
        $active = new User();
        $active->isActive = User::STATUS_ACTIVE;
        self::assertTrue($active->isActive());
        self::assertTrue($active->getIsActive());

        $inactive = new User();
        $inactive->isActive = User::STATUS_INACTIVE;
        self::assertFalse($inactive->isActive());
        self::assertFalse($inactive->getIsActive());
    }

    public function testAuthAccessorsReturnEntityFields(): void
    {
        $user = new User();
        $user->email = 'admin@example.com';
        $user->displayName = 'Admin User';
        $user->setPassword('hashed-secret');

        self::assertSame('admin@example.com', $user->getAuthIdentifier());
        self::assertSame('admin@example.com', $user->getEmail());
        self::assertSame('Admin User', $user->getDisplayName());
        self::assertSame('hashed-secret', $user->getAuthPassword());
        self::assertSame('hashed-secret', $user->getPassword());
    }

    public function testRedirectPathDefaultsToRoot(): void
    {
        self::assertSame('/', (new User())->getRedirectPath());
    }

    public function testSupportsPasswordAndMagicLinkAuthenticatorsOnly(): void
    {
        $user = new User();

        self::assertTrue($user->supportsAuthenticator('password'));
        self::assertTrue($user->supportsAuthenticator('magic_link'));
        self::assertFalse($user->supportsAuthenticator('saml'));
    }

    public function testGetIpv4ConvertsStoredLongToDottedString(): void
    {
        $user = new User();
        $user->ipv4 = (string) ip2long('192.168.0.1');

        self::assertSame('192.168.0.1', $user->getIpv4());
    }

    public function testNameAccessorsReturnGivenValues(): void
    {
        $user = new User();
        $user->firstName = 'Ada';
        $user->lastName = 'Lovelace';

        self::assertSame('Ada', $user->getFirstName());
        self::assertSame('Lovelace', $user->getLastName());
    }
}
