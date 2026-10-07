<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Delegate\Validator\Delegate as DelegateValidator;
use Solidarity\Tests\Stub\CsrfFalseStub;
use Solidarity\Tests\Stub\CsrfTrueStub;

#[CoversClass(DelegateValidator::class)]
final class DelegateValidatorTest extends TestCase
{
    public function testInvalidEmailFails(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertFalse($validator->isValid(['email' => 'not-an-email', 'schools' => []]));
        self::assertArrayHasKey('general', $validator->getMessages());
    }

    public function testDuplicateSchoolSelectionFails(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertFalse($validator->isValid(['email' => '', 'schools' => [1, 1]]));
        self::assertArrayHasKey('schools', $validator->getMessages());
    }

    /** A school may have several delegates - picking one another delegate holds is fine. */
    public function testSchoolSharedWithAnotherDelegatePasses(): void
    {
        $validator = $this->validator(csrfValid: true);

        self::assertTrue($validator->isValid(['email' => 'delegate@example.com', 'schools' => [2, 3], 'id' => 5]));
        self::assertSame([], $validator->getMessages());
    }

    public function testInvalidCsrfFails(): void
    {
        $validator = $this->validator(csrfValid: false);

        self::assertFalse($validator->isValid(['email' => 'delegate@example.com', 'schools' => []]));
        self::assertArrayHasKey('general', $validator->getMessages());
    }

    private function validator(bool $csrfValid): DelegateValidator
    {
        $csrf = $csrfValid ? new CsrfTrueStub() : new CsrfFalseStub();

        return new DelegateValidator($csrf, $this->createStub(DelegateRepository::class));
    }
}
