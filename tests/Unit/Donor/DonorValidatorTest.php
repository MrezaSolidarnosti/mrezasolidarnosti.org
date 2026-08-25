<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Donor\Validator\Donor as DonorValidator;
use Solidarity\Tests\Stub\CsrfTrueStub;

#[CoversClass(DonorValidator::class)]
final class DonorValidatorTest extends TestCase
{
    public function testValidEmailPasses(): void
    {
        $validator = new DonorValidator(new CsrfTrueStub());

        self::assertTrue($validator->isValid([
            'email' => 'donor@example.com',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
        ]));
        self::assertSame([], $validator->getMessages());
    }

    public function testInvalidEmailFails(): void
    {
        $validator = new DonorValidator(new CsrfTrueStub());

        self::assertFalse($validator->isValid([
            'email' => 'not-an-email',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
        ]));
        self::assertArrayHasKey('email', $validator->getMessages());
    }
}
