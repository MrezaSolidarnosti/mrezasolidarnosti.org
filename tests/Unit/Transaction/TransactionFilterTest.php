<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;
use Skeletor\Core\Security\Csrf;

#[CoversClass(TransactionFilter::class)]
final class TransactionFilterTest extends TestCase
{
    public function testFilterCastsNumericFieldsAndStripsCsrf(): void
    {
        $filter = new TransactionFilter($this->validator(valid: true));

        $result = $filter->filter([
            'id' => '9',
            'beneficiary' => 1,
            'project' => 2,
            'period' => 3,
            'amount' => '500',
            'amountEur' => '4',
            'comment' => 'note',
            'status' => '7',
            'donor' => 5,
            'accountNumber' => 'acc',
            'instructions' => 'ins',
            'skipCsrf' => true,
            Csrf::TOKEN_NAME => 'token',
        ]);

        self::assertSame(9, $result['id']);
        self::assertSame(500, $result['amount']);
        self::assertSame(7, $result['status']);
        self::assertTrue($result['skipCsrf']);
        self::assertArrayNotHasKey(Csrf::TOKEN_NAME, $result);
    }

    public function testSkipCsrfDefaultsToFalse(): void
    {
        $filter = new TransactionFilter($this->validator(valid: true));

        $result = $filter->filter([
            'beneficiary' => 1,
            'project' => 2,
            'period' => 3,
            'donor' => 5,
        ]);

        self::assertFalse($result['skipCsrf']);
        self::assertSame(0, $result['amount']);
    }

    public function testAllocatorFieldsPassThroughAndDefault(): void
    {
        $filter = new TransactionFilter($this->validator(valid: true));
        $base = ['beneficiary' => 1, 'project' => 2, 'period' => 3, 'donor' => 5];

        // Supplied by the allocator → preserved for the factory / validator.
        $with = $filter->filter($base + ['paymentType' => 1, 'skipDonorPaymentCheck' => true]);
        self::assertSame(1, $with['paymentType']);
        self::assertTrue($with['skipDonorPaymentCheck']);

        // Absent (e.g. legacy import) → null/false so the factory re-derives + validator checks.
        $without = $filter->filter($base);
        self::assertNull($without['paymentType']);
        self::assertFalse($without['skipDonorPaymentCheck']);
    }

    public function testFilterThrowsWhenValidatorFails(): void
    {
        $filter = new TransactionFilter($this->validator(valid: false));

        $this->expectException(ValidatorException::class);

        $filter->filter(['donor' => 5]);
    }

    private function validator(bool $valid): TransactionValidator
    {
        $validator = $this->createStub(TransactionValidator::class);
        $validator->method('isValid')->willReturn($valid);

        return $validator;
    }
}
