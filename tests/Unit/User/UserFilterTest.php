<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\User\Entity\User;
use Solidarity\User\Filter\User as UserFilter;
use Solidarity\User\Validator\User as UserValidator;
use Skeletor\Core\Security\Csrf;

#[CoversClass(UserFilter::class)]
final class UserFilterTest extends TestCase
{
    public function testAdminRoleClearsDelegateAssignment(): void
    {
        $filter = new UserFilter($this->validator(valid: true));

        $result = $filter->filter($this->postData(['role' => User::ROLE_ADMIN, 'delegate' => 5]));

        self::assertNull($result['delegate']);
        self::assertArrayNotHasKey(Csrf::TOKEN_NAME, $result);
    }

    public function testNonAdminRoleKeepsDelegateAssignment(): void
    {
        $filter = new UserFilter($this->validator(valid: true));

        $result = $filter->filter($this->postData(['role' => User::ROLE_STUFF, 'delegate' => 5]));

        self::assertSame(5, $result['delegate']);
    }

    public function testIsActiveCastToInt(): void
    {
        $filter = new UserFilter($this->validator(valid: true));

        $result = $filter->filter($this->postData(['isActive' => '1']));

        self::assertSame(1, $result['isActive']);
    }

    public function testDisplayNameFallsBackToFullNameWhenEmpty(): void
    {
        $filter = new UserFilter($this->validator(valid: true));

        $result = $filter->filter($this->postData([
            'displayName' => '',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
        ]));

        self::assertStringContainsString('Ada', $result['displayName']);
        self::assertStringContainsString('Lovelace', $result['displayName']);
    }

    public function testFilterThrowsWhenValidatorFails(): void
    {
        $filter = new UserFilter($this->validator(valid: false));

        $this->expectException(ValidatorException::class);

        $filter->filter($this->postData());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function postData(array $overrides = []): array
    {
        return array_merge([
            'id' => 2,
            'email' => 'user@example.com',
            'role' => User::ROLE_STUFF,
            'isActive' => '1',
            'displayName' => 'Admin',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'delegate' => null,
            Csrf::TOKEN_NAME => 'token',
        ], $overrides);
    }

    private function validator(bool $valid): UserValidator
    {
        $validator = $this->createStub(UserValidator::class);
        $validator->method('isValid')->willReturn($valid);

        return $validator;
    }
}
