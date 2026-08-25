<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\Beneficiary;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Transaction\Entity\Project;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\QrCode;

#[CoversClass(QrCode::class)]
final class QrCodeTest extends TestCase
{
    private function makeTransaction(): Transaction
    {
        $project = new Project();
        $project->code = 'MSP';
        $project->name = 'Mreža solidarnosti';

        $beneficiary = new Beneficiary();
        $beneficiary->name = 'Petar Petrović';

        $transaction = new Transaction();
        $transaction->id = 123;
        $transaction->accountNumber = '160-1234567891234-12'; // dashes are stripped
        $transaction->amount = 5000;                           // stored in RSD
        // canBuildFor() reads both, and they are non-nullable typed properties: leaving
        // them unset is an "accessed before initialization" Error, not a false result.
        // Bank transfer + an open status is the only combination an NBS QR applies to.
        $transaction->paymentType = PaymentMethod::TYPE_BANK_TRANSFER;
        $transaction->status = Transaction::STATUS_NEW;
        $transaction->project = $project;
        $transaction->beneficiary = $beneficiary;

        return $transaction;
    }

    public function testBuildsFullIpsPayloadInTagOrder(): void
    {
        $payload = (new QrCode())->buildIpsPayload($this->makeTransaction());

        self::assertSame(
            'K:PR|V:01|C:1|R:160123456789123412|N:Petar Petrović|I:RSD5000,00|SF:289|S:Donacija MSP-123|RO:00123',
            $payload
        );
    }

    public function testAccountNumberIsReducedToDigitsOnly(): void
    {
        $transaction = $this->makeTransaction();
        $transaction->accountNumber = '265-0000001234567-89';

        self::assertStringContainsString('|R:265000000123456789|', (new QrCode())->buildIpsPayload($transaction));
    }

    public function testAmountUsesCommaDecimalAndNoThousandsSeparator(): void
    {
        $transaction = $this->makeTransaction();
        $transaction->amount = 240000;

        self::assertStringContainsString('|I:RSD240000,00|', (new QrCode())->buildIpsPayload($transaction));
    }

    public function testThrowsWhenTransactionHasNoBeneficiary(): void
    {
        $transaction = $this->makeTransaction();
        $transaction->beneficiary = null;

        $this->expectException(\RuntimeException::class);
        (new QrCode())->buildIpsPayload($transaction);
    }

    public function testCanBuildForReflectsBeneficiaryPresence(): void
    {
        $qrCode = new QrCode();
        $transaction = $this->makeTransaction();

        self::assertTrue($qrCode->canBuildFor($transaction));

        $transaction->beneficiary = null;
        self::assertFalse($qrCode->canBuildFor($transaction));
    }

    public function testRecipientNameIsClippedToSeventyChars(): void
    {
        $transaction = $this->makeTransaction();
        $transaction->beneficiary->name = str_repeat('a', 90);

        $payload = (new QrCode())->buildIpsPayload($transaction);

        // Pull the N tag back out and check its length.
        preg_match('/\|N:([^|]*)\|/', $payload, $m);
        self::assertSame(70, mb_strlen($m[1]));
    }

    public function testWhitespaceInRecipientIsCollapsed(): void
    {
        $transaction = $this->makeTransaction();
        $transaction->beneficiary->name = "Petar   \n  Petrović";

        self::assertStringContainsString('|N:Petar Petrović|', (new QrCode())->buildIpsPayload($transaction));
    }
}
