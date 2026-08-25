<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solidarity\Delegate\Entity\Delegate;

#[CoversClass(Delegate::class)]
final class DelegateTest extends TestCase
{
    public function testGetHrStatusReturnsLabelForKnownStatus(): void
    {
        self::assertSame('Nov', Delegate::getHrStatus(Delegate::STATUS_NEW));
        self::assertSame('Verifikovan', Delegate::getHrStatus(Delegate::STATUS_VERIFIED));
        self::assertSame('Problem', Delegate::getHrStatus(Delegate::STATUS_PROBLEM));
    }

    public function testAuthRoleIsFixedAtTen(): void
    {
        self::assertSame(10, (new Delegate())->getAuthRole());
    }

    public function testRedirectPathPointsToBeneficiaryView(): void
    {
        self::assertSame('/beneficiary/view/', (new Delegate())->getRedirectPath());
    }

    public function testAuthPasswordIsNullSincePasswordlessOnly(): void
    {
        self::assertNull((new Delegate())->getAuthPassword());
    }

    /**
     * Only verified delegates may authenticate; every other status is inactive.
     */
    #[DataProvider('statusActivityProvider')]
    public function testIsActiveOnlyForVerifiedStatus(int $status, bool $expectedActive): void
    {
        $delegate = new Delegate();
        $delegate->status = $status;

        self::assertSame($expectedActive, $delegate->isActive());
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function statusActivityProvider(): array
    {
        return [
            'new is inactive'      => [Delegate::STATUS_NEW, false],
            'verified is active'   => [Delegate::STATUS_VERIFIED, true],
            'problem is inactive'  => [Delegate::STATUS_PROBLEM, false],
            'zero is inactive'     => [0, false],
        ];
    }

    public function testSupportsPasswordAndMagicLinkAuthenticatorsOnly(): void
    {
        $delegate = new Delegate();

        self::assertTrue($delegate->supportsAuthenticator('password'));
        self::assertTrue($delegate->supportsAuthenticator('magic_link'));
        self::assertFalse($delegate->supportsAuthenticator('oauth'));
    }

    public function testAuthIdentifierAndAccessorsReturnEntityFields(): void
    {
        $delegate = new Delegate();
        $delegate->email = 'delegate@example.com';
        $delegate->name = 'Test Delegate';

        self::assertSame('delegate@example.com', $delegate->getAuthIdentifier());
        self::assertSame('delegate@example.com', $delegate->getEmail());
        self::assertSame('Test Delegate', $delegate->getDisplayName());
    }
}
