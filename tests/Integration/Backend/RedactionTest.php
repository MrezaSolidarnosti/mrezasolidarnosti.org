<?php

declare(strict_types=1);

namespace Solidarity\Tests\Integration\Backend;

use PHPUnit\Framework\Attributes\CoversClass;
use Solidarity\Backend\Service\Redaction;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Beneficiary\Entity\PaymentMethod as BeneficiaryPaymentMethod;
use Solidarity\Beneficiary\Entity\RegisteredPeriods;
use Solidarity\Donor\Entity\Donor;
use Solidarity\Donor\Entity\PaymentMethod as DonorPaymentMethod;
use Solidarity\Tests\Integration\IntegrationTestCase;
use Solidarity\Transaction\Entity\Transaction;

#[CoversClass(Redaction::class)]
final class RedactionTest extends IntegrationTestCase
{
    private function redaction(): Redaction
    {
        return new Redaction($this->em());
    }

    public function testRedactDonorErasesDonorButKeepsAnonymisedTransaction(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor(email: 'erase-me@example.com');
        $this->linkDonorToProject($donor, $project);
        $pm = $this->createDonorPaymentMethod($donor, $project);
        $beneficiary = $this->createBeneficiary();
        $tx = $this->createTransaction($donor, $beneficiary, $project, $period, 5000);
        $txId = $tx->getId();
        $donorId = $donor->getId();
        $pmId = $pm->getId();

        // Re-fetch (managed, lazy collections) exactly as the controller does via getById().
        $this->em()->clear();
        $this->redaction()->redactDonor($this->em()->find(Donor::class, $donorId));
        $this->em()->clear();

        self::assertNull($this->em()->find(Donor::class, $donorId), 'donor row is erased');
        self::assertNull($this->em()->find(DonorPaymentMethod::class, $pmId), 'donor pledge removed');
        $kept = $this->em()->find(Transaction::class, $txId);
        self::assertNotNull($kept, 'transaction kept for accounting');
        self::assertNull($kept->donor, 'donor link detached');
        self::assertSame(5000, $kept->amount, 'amount preserved');
    }

    public function testRedactBeneficiaryStripsAccountDetailsFromTransactions(): void
    {
        $project = $this->createProject('MSPR');
        $period = $this->createPeriod($project);
        $donor = $this->createDonor();
        $beneficiary = $this->createBeneficiary(name: 'Someone Private');
        $benPm = $this->createBeneficiaryPaymentMethod($beneficiary, accountNumber: '160600000027894822');
        $rp = $this->createRegisteredPeriod($beneficiary, $project, $period, 30000);
        $tx = $this->createTransaction($donor, $beneficiary, $project, $period, 5000);
        // account details are copied onto the transaction on real creation
        $tx->accountNumber = '160600000027894822';
        $tx->instructions = 'wire instructions';
        $this->em()->flush();
        $txId = $tx->getId();
        $benId = $beneficiary->getId();
        $benPmId = $benPm->getId();
        $rpId = $rp->getId();

        $this->em()->clear();
        $this->redaction()->redactBeneficiary($this->em()->find(Beneficiary::class, $benId));
        $this->em()->clear();

        self::assertNull($this->em()->find(Beneficiary::class, $benId), 'beneficiary erased');
        self::assertNull($this->em()->find(BeneficiaryPaymentMethod::class, $benPmId), 'bank account removed');
        self::assertNull($this->em()->find(RegisteredPeriods::class, $rpId), 'registered period removed');
        $kept = $this->em()->find(Transaction::class, $txId);
        self::assertNotNull($kept, 'transaction kept for accounting');
        self::assertNull($kept->beneficiary, 'beneficiary link detached');
        self::assertNull($kept->accountNumber, 'account number stripped');
        self::assertNull($kept->instructions, 'instructions stripped');
        self::assertSame(5000, $kept->amount, 'amount preserved');
    }
}
