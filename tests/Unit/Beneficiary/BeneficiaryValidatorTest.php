<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Validator\Beneficiary as BeneficiaryValidator;

/**
 * Covers the non-database validation paths. The account-number control-digit
 * (mod97) and cross-beneficiary uniqueness checks run only for bank-transfer
 * (type === 1) payment methods and hit the EntityManager, so they belong in
 * the integration suite rather than here.
 */
#[CoversClass(BeneficiaryValidator::class)]
final class BeneficiaryValidatorTest extends TestCase
{
    public function testValidPayloadPasses(): void
    {
        $validator = $this->validator();

        self::assertTrue($validator->isValid($this->validPayload()));
        self::assertSame([], $validator->getMessages());
    }

    public function testEmptyNameFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['name'] = '';

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('name', $validator->getMessages());
    }

    public function testMissingDelegateFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['createdBy'] = 0;

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('createdBy', $validator->getMessages());
    }

    public function testNoPaymentMethodsFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['paymentMethods'] = [];

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('paymentMethods', $validator->getMessages());
    }

    public function testNoRegisteredPeriodsFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['registeredPeriods'] = [];

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('registeredPeriods', $validator->getMessages());
    }

    public function testNonPositiveAmountFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['registeredPeriods'] = [['period' => 1, 'amount' => 0]];

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('registeredPeriods', $validator->getMessages());
    }

    public function testAmountAboveMonthlyLimitFails(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['registeredPeriods'] = [['period' => 1, 'amount' => Beneficiary::MONTHLY_LIMIT + 1]];

        self::assertFalse($validator->isValid($payload));
        self::assertArrayHasKey('registeredPeriods', $validator->getMessages());
    }

    public function testAmountExactlyAtMonthlyLimitPasses(): void
    {
        $validator = $this->validator();
        $payload = $this->validPayload();
        $payload['registeredPeriods'] = [['period' => 1, 'amount' => Beneficiary::MONTHLY_LIMIT]];

        self::assertTrue($validator->isValid($payload));
    }

    /**
     * A valid payload that exercises no database-backed checks: the single
     * payment method is type 2 (wire), which skips account-number validation.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Test Beneficiary',
            'createdBy' => 5,
            'paymentMethods' => [['type' => 2]],
            'registeredPeriods' => [['period' => 1, 'amount' => 1000]],
        ];
    }

    private function validator(): BeneficiaryValidator
    {
        // A bare stub reports no entity type and no role, so isAdmin() is false and every
        // limit in here applies — which is what these tests assert.
        return new BeneficiaryValidator(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(Session::class),
        );
    }
}
