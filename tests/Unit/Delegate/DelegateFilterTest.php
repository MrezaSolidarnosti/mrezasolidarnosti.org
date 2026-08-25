<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\Delegate\Filter\Delegate as DelegateFilter;
use Solidarity\Delegate\Validator\Delegate as DelegateValidator;
use Skeletor\Core\Security\Csrf;

#[CoversClass(DelegateFilter::class)]
final class DelegateFilterTest extends TestCase
{
    public function testFilterSanitizesDataAndStripsCsrf(): void
    {
        $filter = new DelegateFilter($this->validator(valid: true));

        $result = $filter->filter([
            'id' => '3',
            'name' => 'Petar',
            'email' => 'delegate@example.com',
            'phone' => '0601234567',
            'verifiedBy' => 'Admin',
            'schools' => [1, 2],
            'projects' => [1],
            'comment' => 'note',
            'adminComment' => 'admin note',
            'status' => 2,
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertSame(3, $result['id']);
        self::assertSame('Petar', $result['name']);
        self::assertSame([1, 2], $result['schools']);
        self::assertArrayNotHasKey(Csrf::TOKEN_NAME, $result);
    }

    public function testTransliteratesCyrillicNameToLatin(): void
    {
        $filter = new DelegateFilter($this->validator(valid: true));

        $result = $filter->filter([
            'name' => 'Петар',
            'email' => 'delegate@example.com',
            'phone' => '060',
            'projects' => [1],
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertSame('Petar', $result['name']);
    }

    public function testDefaultsForOptionalFields(): void
    {
        $filter = new DelegateFilter($this->validator(valid: true));

        $result = $filter->filter([
            'name' => 'Petar',
            'phone' => '060',
            'projects' => [1],
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertNull($result['email']);
        self::assertSame('', $result['verifiedBy']);
        self::assertSame([], $result['schools']);
        self::assertSame(1, $result['status']);
    }

    public function testFilterThrowsWhenValidatorFails(): void
    {
        $filter = new DelegateFilter($this->validator(valid: false));

        $this->expectException(ValidatorException::class);

        $filter->filter([
            'name' => 'Petar',
            'phone' => '060',
            'projects' => [1],
            Csrf::TOKEN_NAME => 'token',
        ]);
    }

    private function validator(bool $valid): DelegateValidator
    {
        $validator = $this->createStub(DelegateValidator::class);
        $validator->method('isValid')->willReturn($valid);

        return $validator;
    }
}
