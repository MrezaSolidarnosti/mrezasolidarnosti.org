<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Validator\Beneficiary as BeneficiaryValidator;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\User\Entity\User;

/**
 * Covers the database-backed account-number safety checks that the unit-level
 * BeneficiaryValidatorTest deferred: the mod97 control digit, the defunct/forbidden
 * bank prefixes, and cross-beneficiary account uniqueness.
 */
#[CoversClass(BeneficiaryValidator::class)]
final class BeneficiaryValidatorTest extends IntegrationTestCase
{
    // 16-zero base -> mod97 control digit "98" -> a valid account number.
    private const VALID_ACCOUNT = '000000000000000098';

    public function testValidBankAccountPasses(): void
    {
        $validator = $this->validator();

        self::assertTrue($validator->isValid($this->payload(self::VALID_ACCOUNT)));
        self::assertSame([], $validator->getMessages());
    }

    public function testRejectsRepublicBudgetAccount(): void
    {
        $validator = $this->validator();

        $validator->isValid($this->payload('840' . str_repeat('0', 15)));

        self::assertStringContainsString('budzetu', $this->paymentMethodMessages($validator));
    }

    public function testRejectsDefunctEurobankAccount(): void
    {
        $validator = $this->validator();

        $validator->isValid($this->payload('150' . str_repeat('0', 15)));

        self::assertStringContainsString('Eurobank', $this->paymentMethodMessages($validator));
    }

    public function testRejectsDefunctMtsAccount(): void
    {
        $validator = $this->validator();

        $validator->isValid($this->payload('360' . str_repeat('0', 15)));

        self::assertStringContainsString('MTS', $this->paymentMethodMessages($validator));
    }

    public function testRejectsInvalidControlNumber(): void
    {
        $validator = $this->validator();

        // 18 zeros: the control digits should be "98", so "00" is invalid.
        $validator->isValid($this->payload(str_repeat('0', 18)));

        self::assertStringContainsString('kontrolni broj', $this->paymentMethodMessages($validator));
    }

    public function testRejectsAccountAlreadyAssignedToAnotherBeneficiary(): void
    {
        // Seed an existing beneficiary that already owns the account.
        $existing = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($existing, type: 1, accountNumber: self::VALID_ACCOUNT);

        $validator = $this->validator();
        $validator->isValid($this->payload(self::VALID_ACCOUNT));

        self::assertStringContainsString('već dodeljen', $this->paymentMethodMessages($validator));
    }

    public function testAllowsBeneficiaryToKeepItsOwnAccountOnUpdate(): void
    {
        // A beneficiary already owns the account...
        $beneficiary = $this->createBeneficiary();
        $this->createBeneficiaryPaymentMethod($beneficiary, type: 1, accountNumber: self::VALID_ACCOUNT);

        // ...and edits itself (payload carries its own id) without changing the account.
        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['id'] = $beneficiary->getId();

        $validator = $this->validator();
        $validator->isValid($payload);

        self::assertStringNotContainsString('već dodeljen', $this->paymentMethodMessages($validator));
    }

    public function testAPeriodWithItsOwnMaximumOverridesTheGlobalMonthlyLimit(): void
    {
        // A round where less money is available. The field on the period form promises this;
        // until now the filter dropped it and nothing read it, so the global limit applied
        // whatever an admin typed.
        $period = $this->createPeriod($this->createProject(), maxAmount: 50000);
        $validator = $this->validator();

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 60000]];

        self::assertFalse($validator->isValid($payload));
        self::assertStringContainsString(
            'veći od limita od 50,000',
            implode(' | ', $validator->getMessages()['registeredPeriods'] ?? []),
        );
    }

    public function testAnAdminMayExceedThePeriodMaximum(): void
    {
        // The limit is a guard rail for delegates and staff. An admin typing a figure above it
        // is making a deliberate exception, and there is nowhere else in the UI to record one.
        $period = $this->createPeriod($this->createProject(), maxAmount: 50000);
        $validator = new BeneficiaryValidator($this->em(), $this->sessionFor('user', User::ROLE_ADMIN));

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 600000]];

        self::assertTrue($validator->isValid($payload));
    }

    public function testStaffAreStillHeldToThePeriodMaximum(): void
    {
        // Only ROLE_ADMIN is exempt — the exemption must not leak to every logged-in user.
        $period = $this->createPeriod($this->createProject(), maxAmount: 50000);
        $validator = new BeneficiaryValidator($this->em(), $this->sessionFor('user', User::ROLE_STUFF));

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 600000]];

        self::assertFalse($validator->isValid($payload));
    }

    public function testADelegateIsStillHeldToThePeriodMaximum(): void
    {
        // Delegates number their own role separately, so the entity type is checked as well —
        // otherwise a delegate role that happened to equal 1 would inherit the exemption.
        $period = $this->createPeriod($this->createProject(), maxAmount: 50000);
        $validator = new BeneficiaryValidator($this->em(), $this->sessionFor('delegate', 10));

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 600000]];

        self::assertFalse($validator->isValid($payload));
    }

    public function testAnAdminStillCannotSaveANonPositiveAmount(): void
    {
        // The exemption covers the limit, not arithmetic: zero is nonsense whoever types it.
        $period = $this->createPeriod($this->createProject(), maxAmount: 50000);
        $validator = new BeneficiaryValidator($this->em(), $this->sessionFor('user', User::ROLE_ADMIN));

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 0]];

        self::assertFalse($validator->isValid($payload));
    }

    /** A validator for someone who is NOT exempt, which is what most of these tests want. */
    private function validator(): BeneficiaryValidator
    {
        return new BeneficiaryValidator($this->em(), $this->sessionFor('user', User::ROLE_STUFF));
    }

    private function sessionFor(string $entityType, int $role): Session
    {
        $session = $this->createStub(Session::class);
        $session->method('getLoggedInEntityType')->willReturn($entityType);
        $session->method('getLoggedInRole')->willReturn($role);

        return $session;
    }

    public function testAPeriodMaximumCanAlsoBeHigherThanTheGlobalLimit(): void
    {
        // It is an override, not a cap on a cap — a catch-up round can allow more.
        $period = $this->createPeriod($this->createProject(), maxAmount: 500000);
        $validator = $this->validator();

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => $period->getId(), 'amount' => 300000]];

        self::assertTrue($validator->isValid($payload));
    }

    public function testAPeriodWithNoMaximumOfItsOwnFallsBackToTheGlobalLimit(): void
    {
        // Blank and 0 both mean "use the global limit", which is what the hint under the
        // field says. Treating 0 as a real limit would make every legacy period reject
        // everything — MigrateLegacy writes maxAmount = 0 for all of them.
        $period = $this->createPeriod($this->createProject(), maxAmount: 0);
        $validator = $this->validator();

        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [
            ['period' => $period->getId(), 'amount' => Beneficiary::MONTHLY_LIMIT],
        ];

        self::assertTrue($validator->isValid($payload));

        $payload['registeredPeriods'] = [
            ['period' => $period->getId(), 'amount' => Beneficiary::MONTHLY_LIMIT + 1],
        ];
        $validator = $this->validator();

        self::assertFalse($validator->isValid($payload));
    }

    public function testAStoredRegistrationWithNoUsablePeriodDoesNotBlockTheSave(): void
    {
        // A row the form could not render posts back without a usable period. It is
        // preserved as-is rather than rewritten, so demanding a period here would refuse a
        // save over a row the user never touched — and could not have fixed if they wanted
        // to, since the option they would need is exactly the one that failed to render.
        $validator = $this->validator();
        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['id' => 17, 'period' => 0, 'amount' => 40000]];

        self::assertTrue($validator->isValid($payload));
        self::assertSame([], $validator->getMessages());
    }

    public function testANewRowWithNoPeriodIsStillRejected(): void
    {
        // No id means nothing is being preserved — the user really did leave it blank.
        $validator = $this->validator();
        $payload = $this->payload(self::VALID_ACCOUNT);
        $payload['registeredPeriods'] = [['period' => 0, 'amount' => 40000]];

        self::assertFalse($validator->isValid($payload));
        self::assertStringContainsString(
            'Period je neophodan',
            implode(' | ', $validator->getMessages()['registeredPeriods'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $accountNumber): array
    {
        return [
            'name' => 'Test Beneficiary',
            'createdBy' => 1,
            'paymentMethods' => [
                ['type' => 1, 'accountNumber' => $accountNumber],
            ],
            'registeredPeriods' => [
                ['period' => 1, 'amount' => 1000],
            ],
        ];
    }

    private function paymentMethodMessages(BeneficiaryValidator $validator): string
    {
        return implode(' | ', $validator->getMessages()['paymentMethods'] ?? []);
    }
}
