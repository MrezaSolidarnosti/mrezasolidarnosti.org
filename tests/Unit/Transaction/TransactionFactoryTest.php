<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Factory\TransactionFactory;

#[CoversClass(TransactionFactory::class)]
final class TransactionFactoryTest extends TestCase
{
    public function testMatchPaymentTypeReturnsBeneficiaryDetailsForMatchingType(): void
    {
        $donor = $this->donorWithPaymentTypes(DonorPaymentMethod::TYPE_WIRE_TRANSFER);
        $beneficiary = $this->beneficiaryWithPaymentMethod(
            BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER,
            accountNumber: 'RS35260005601001611379',
            wireInstructions: 'SWIFT: TESTRS22',
        );

        $match = TransactionFactory::matchPaymentType($donor, $beneficiary);

        self::assertSame(DonorPaymentMethod::TYPE_WIRE_TRANSFER, $match['paymentType']);
        self::assertSame('RS35260005601001611379', $match['accountNumber']);
        self::assertSame('SWIFT: TESTRS22', $match['instructions']);
    }

    public function testMatchPaymentTypePicksTheFirstCommonType(): void
    {
        // Donor offers bank-transfer first, then wire; beneficiary only accepts wire.
        $donor = $this->donorWithPaymentTypes(
            DonorPaymentMethod::TYPE_BANK_TRANSFER,
            DonorPaymentMethod::TYPE_WIRE_TRANSFER,
        );
        $beneficiary = $this->beneficiaryWithPaymentMethod(BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER);

        $match = TransactionFactory::matchPaymentType($donor, $beneficiary);

        self::assertSame(DonorPaymentMethod::TYPE_WIRE_TRANSFER, $match['paymentType']);
    }

    public function testMatchPaymentTypeScopesToTheProjectPledge(): void
    {
        $projectA = $this->projectWithId(1);
        $projectB = $this->projectWithId(2);

        // Donor pledged bank transfer to project A and wire transfer to project B.
        $donor = new Donor();
        $donor->paymentMethods = new ArrayCollection([
            $this->donorPaymentMethodFor(DonorPaymentMethod::TYPE_BANK_TRANSFER, $projectA),
            $this->donorPaymentMethodFor(DonorPaymentMethod::TYPE_WIRE_TRANSFER, $projectB),
        ]);

        // Beneficiary accepts both types.
        $bankPm = new BeneficiaryPaymentMethod();
        $bankPm->type = BeneficiaryPaymentMethod::TYPE_BANK_TRANSFER;
        $bankPm->accountNumber = '160600000027894822';
        $bankPm->wireInstructions = null;
        $wirePm = new BeneficiaryPaymentMethod();
        $wirePm->type = BeneficiaryPaymentMethod::TYPE_WIRE_TRANSFER;
        $wirePm->accountNumber = null;
        $wirePm->wireInstructions = 'SWIFT: TESTRS22';
        $beneficiary = new Beneficiary();
        $beneficiary->paymentMethods = new ArrayCollection([$bankPm, $wirePm]);

        // For project B the donor pledged wire, so wire must be matched — even though the
        // unscoped match would return bank (the donor's first, project-A, pledge).
        self::assertSame(
            DonorPaymentMethod::TYPE_WIRE_TRANSFER,
            TransactionFactory::matchPaymentType($donor, $beneficiary, $projectB)['paymentType'],
        );
        // Sanity check on the unscoped behaviour: first common type wins (bank).
        self::assertSame(
            DonorPaymentMethod::TYPE_BANK_TRANSFER,
            TransactionFactory::matchPaymentType($donor, $beneficiary)['paymentType'],
        );
    }

    public function testMatchPaymentTypeThrowsWhenNoCommonType(): void
    {
        $donor = $this->donorWithPaymentTypes(DonorPaymentMethod::TYPE_BANK_TRANSFER);
        $beneficiary = $this->beneficiaryWithPaymentMethod(BeneficiaryPaymentMethod::TYPE_MONEYGRAM);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No matching payment type found');

        TransactionFactory::matchPaymentType($donor, $beneficiary);
    }

    private function donorWithPaymentTypes(int ...$types): Donor
    {
        $donor = new Donor();
        $methods = [];
        foreach ($types as $type) {
            $pm = new DonorPaymentMethod();
            $pm->type = $type;
            $methods[] = $pm;
        }
        $donor->paymentMethods = new ArrayCollection($methods);

        return $donor;
    }

    private function beneficiaryWithPaymentMethod(
        int $type,
        ?string $accountNumber = null,
        ?string $wireInstructions = null,
    ): Beneficiary {
        $beneficiary = new Beneficiary();
        $pm = new BeneficiaryPaymentMethod();
        $pm->type = $type;
        $pm->accountNumber = $accountNumber;
        $pm->wireInstructions = $wireInstructions;
        $beneficiary->paymentMethods = new ArrayCollection([$pm]);

        return $beneficiary;
    }

    private function projectWithId(int $id): Project
    {
        $project = new Project();
        $project->id = $id;

        return $project;
    }

    private function donorPaymentMethodFor(int $type, Project $project): DonorPaymentMethod
    {
        $pm = new DonorPaymentMethod();
        $pm->type = $type;
        $pm->project = $project;

        return $pm;
    }
}
