<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Delegate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Delegate\Validator\Delegate as DelegateValidator;
use Solidarity\School\Entity\School;
use Solidarity\School\Repository\SchoolRepository;
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
        $school = new School();
        $school->delegate = null;
        $validator = $this->validator(csrfValid: true, school: $school);

        self::assertFalse($validator->isValid(['email' => '', 'schools' => [1, 1]]));
        self::assertArrayHasKey('schools', $validator->getMessages());
    }

    public function testSchoolAssignedToAnotherDelegateFails(): void
    {
        $otherDelegate = new Delegate();
        $otherDelegate->id = 9;
        $school = new School();
        $school->name = 'Test School';
        $school->delegate = $otherDelegate;

        $validator = $this->validator(csrfValid: true, school: $school);

        self::assertFalse($validator->isValid(['email' => '', 'schools' => [2], 'id' => 5]));
        self::assertArrayHasKey('schools', $validator->getMessages());
    }

    public function testSchoolAlreadyOwnedBySameDelegatePasses(): void
    {
        $sameDelegate = new Delegate();
        $sameDelegate->id = 5;
        $school = new School();
        $school->delegate = $sameDelegate;

        $validator = $this->validator(csrfValid: true, school: $school);

        self::assertTrue($validator->isValid(['email' => 'delegate@example.com', 'schools' => [2], 'id' => 5]));
        self::assertSame([], $validator->getMessages());
    }

    public function testInvalidCsrfFails(): void
    {
        $validator = $this->validator(csrfValid: false);

        self::assertFalse($validator->isValid(['email' => 'delegate@example.com', 'schools' => []]));
        self::assertArrayHasKey('general', $validator->getMessages());
    }

    private function validator(bool $csrfValid, ?School $school = null): DelegateValidator
    {
        $csrf = $csrfValid ? new CsrfTrueStub() : new CsrfFalseStub();

        $schoolRepo = $this->createStub(SchoolRepository::class);
        $schoolRepo->method('getById')->willReturn($school);

        return new DelegateValidator($csrf, $this->createStub(DelegateRepository::class), $schoolRepo);
    }
}
