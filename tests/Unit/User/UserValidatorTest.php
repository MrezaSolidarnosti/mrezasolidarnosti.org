<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\User;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\InvalidFormTokenException;
use Solidarity\Tests\Stub\CsrfFalseStub;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\User\Entity\User;
use Solidarity\User\Validator\User as UserValidator;

#[CoversClass(UserValidator::class)]
final class UserValidatorTest extends TestCase
{
    public function testInvalidCsrfThrows(): void
    {
        $validator = $this->validator(csrfValid: false);

        $this->expectException(InvalidFormTokenException::class);

        $validator->isValid(['displayName' => 'Valid', 'role' => User::ROLE_ADMIN]);
    }

    public function testShortDisplayNameFails(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertFalse($validator->isValid(['displayName' => 'A', 'role' => User::ROLE_ADMIN]));
        self::assertArrayHasKey('displayName', $validator->getMessages());
    }

    public function testZeroRoleFails(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertFalse($validator->isValid(['displayName' => 'Valid Name', 'role' => 0]));
        self::assertArrayHasKey('role', $validator->getMessages());
    }

    public function testValidDataPasses(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertTrue($validator->isValid(['displayName' => 'Valid Name', 'role' => User::ROLE_STUFF]));
        self::assertSame([], $validator->getMessages());
    }

    private function validator(bool $csrfValid): UserValidator
    {
        $csrf = $csrfValid ? new CsrfTrueStub() : new CsrfFalseStub();

        return new UserValidator($this->createStub(EntityManagerInterface::class), $csrf);
    }
}
