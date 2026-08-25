<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\Core\Validator\ValidatorException;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Validator\Donor as DonorValidator;
use Skeletor\Core\Security\Csrf;

#[CoversClass(DonorFilter::class)]
final class DonorFilterTest extends TestCase
{
    public function testFilterSanitizesDataAndParsesPaymentMethods(): void
    {
        $filter = new DonorFilter($this->validator(valid: true));

        $result = $filter->filter($this->postData());

        self::assertSame(5, $result['id']); // cast to int
        self::assertArrayNotHasKey(Csrf::TOKEN_NAME, $result);
        self::assertCount(1, $result['paymentMethods']);
        self::assertSame(
            ['project' => 3, 'type' => 2, 'monthly' => 1, 'amount' => 100, 'currency' => 2],
            $result['paymentMethods'][0],
        );
    }

    public function testFilterSkipsPlaceholderPaymentMethodRows(): void
    {
        $filter = new DonorFilter($this->validator(valid: true));
        $post = $this->postData();
        $post['paymentMethods'] = [
            ['paymentType' => '-1', 'project' => '3'],   // placeholder type -> skipped
            ['paymentType' => '2', 'project' => '-1'],   // placeholder project -> skipped
            ['paymentType' => '', 'project' => '3'],     // empty type -> skipped
        ];

        $result = $filter->filter($post);

        self::assertSame([], $result['paymentMethods']);
    }

    public function testFilterThrowsWhenValidatorFails(): void
    {
        $filter = new DonorFilter($this->validator(valid: false));

        $this->expectException(ValidatorException::class);

        $filter->filter($this->postData());
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(): array
    {
        return [
            'id' => '5',
            'email' => 'donor@example.com',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'wantsToDonateTo' => 1,
            'comment' => 'note',
            'isActive' => 1,
            'projects' => [1],
            'status' => 2,
            Csrf::TOKEN_NAME => 'token',
            'paymentMethods' => [
                ['paymentType' => '2', 'project' => '3', 'monthly' => '1', 'amount' => '100', 'currency' => '2'],
            ],
        ];
    }

    private function validator(bool $valid): DonorValidator
    {
        $validator = $this->createStub(DonorValidator::class);
        $validator->method('isValid')->willReturn($valid);

        return $validator;
    }
}
