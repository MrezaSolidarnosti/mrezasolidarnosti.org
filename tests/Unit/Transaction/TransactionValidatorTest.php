<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Donor\Repository\DonorRepository;
use Solidarity\Tests\Stub\CsrfFalseStub;
use Solidarity\Tests\Stub\CsrfTrueStub;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Validator\Transaction as TransactionValidator;

#[CoversClass(TransactionValidator::class)]
final class TransactionValidatorTest extends TestCase
{
    private const PROJECT_ID = 1;

    public function testFailsWhenDonorHasNoPaymentMethodForProject(): void
    {
        $donor = $this->donorWithPaymentMethod(projectId: 99, type: DonorPaymentMethod::TYPE_WIRE_TRANSFER);
        $beneficiary = $this->beneficiaryWithType(BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER);

        $validator = $this->validator($donor, $beneficiary, csrfValid: true);

        self::assertFalse($validator->isValid($this->payload()));
        self::assertArrayHasKey('donor', $validator->getMessages());
    }

    public function testFailsWhenNoMatchingPaymentTypeBetweenDonorAndBeneficiary(): void
    {
        $donor = $this->donorWithPaymentMethod(self::PROJECT_ID, DonorPaymentMethod::TYPE_BANK_TRANSFER);
        $beneficiary = $this->beneficiaryWithType(BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER);

        $validator = $this->validator($donor, $beneficiary, csrfValid: true);

        self::assertFalse($validator->isValid($this->payload()));
        self::assertArrayHasKey('donor', $validator->getMessages());
    }

    public function testPassesWhenDonorAndBeneficiaryShareTypeForProject(): void
    {
        $donor = $this->donorWithPaymentMethod(self::PROJECT_ID, DonorPaymentMethod::TYPE_WIRE_TRANSFER);
        $beneficiary = $this->beneficiaryWithType(BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER);

        $validator = $this->validator($donor, $beneficiary, csrfValid: true);

        self::assertTrue($validator->isValid($this->payload()));
        self::assertSame([], $validator->getMessages());
    }

    public function testSkipDonorPaymentCheckBypassesTheDonorMethodValidation(): void
    {
        // Donor has no payment method for the project — normally rejected...
        $donor = $this->donorWithPaymentMethod(projectId: 99, type: DonorPaymentMethod::TYPE_WIRE_TRANSFER);
        $beneficiary = $this->beneficiaryWithType(BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER);

        $validator = $this->validator($donor, $beneficiary, csrfValid: true);

        // ...but allocator-generated transactions set the flag (matching already done).
        $payload = $this->payload();
        $payload['skipDonorPaymentCheck'] = true;

        self::assertTrue($validator->isValid($payload));
        self::assertSame([], $validator->getMessages());
    }

    public function testFailsOnInvalidCsrfWhenNotSkipped(): void
    {
        $validator = $this->validator(
            $this->createStub(Donor::class),
            $this->createStub(Beneficiary::class),
            csrfValid: false,
        );

        self::assertFalse($validator->isValid(['skipCsrf' => false]));
        self::assertArrayHasKey('general', $validator->getMessages());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'donor' => 1,
            'beneficiary' => 1,
            'project' => self::PROJECT_ID,
            'skipCsrf' => true,
        ];
    }

    private function donorWithPaymentMethod(int $projectId, int $type): Donor
    {
        $project = new Project();
        $project->id = $projectId;

        $pm = new DonorPaymentMethod();
        $pm->project = $project;
        $pm->type = $type;

        $donor = new Donor();
        $donor->paymentMethods = new ArrayCollection([$pm]);

        return $donor;
    }

    private function beneficiaryWithType(int $type): Beneficiary
    {
        $pm = new BeneficiaryPaymentMethod();
        $pm->type = $type;

        $beneficiary = new Beneficiary();
        $beneficiary->paymentMethods = new ArrayCollection([$pm]);

        return $beneficiary;
    }

    private function validator(Donor $donor, Beneficiary $beneficiary, bool $csrfValid): TransactionValidator
    {
        $csrf = $csrfValid ? new CsrfTrueStub() : new CsrfFalseStub();

        $donorRepo = $this->createStub(DonorRepository::class);
        $donorRepo->method('getById')->willReturn($donor);

        $beneficiaryRepo = $this->createStub(BeneficiaryRepository::class);
        $beneficiaryRepo->method('getById')->willReturn($beneficiary);

        return new TransactionValidator($csrf, $donorRepo, $beneficiaryRepo);
    }
}
