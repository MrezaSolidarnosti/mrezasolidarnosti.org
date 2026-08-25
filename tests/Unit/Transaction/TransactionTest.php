<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solidarity\Transaction\Entity\Transaction;

#[CoversClass(Transaction::class)]
final class TransactionTest extends TestCase
{
    #[DataProvider('eurToRsdProvider')]
    public function testEurToRsdConvertsAtFixedRate(int $eur, int $expectedRsd): void
    {
        self::assertSame($expectedRsd, Transaction::eurToRsd($eur));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function eurToRsdProvider(): array
    {
        // Rate is 117.5; result is rounded to the nearest integer.
        return [
            'zero'               => [0, 0],
            'one euro rounds up' => [1, 118],   // 117.5 -> 118
            'hundred euros'      => [100, 11750],
            'two euros'          => [2, 235],
        ];
    }

    public function testRsdToEurIsRoundedToTwoDecimals(): void
    {
        // 11750 / 117.5 = 100.0
        self::assertSame(100.0, Transaction::rsdToEur(11750));
    }

    public function testRsdToEurKeepsTwoDecimalPrecision(): void
    {
        // 100 / 117.5 = 0.851... -> 0.85
        self::assertSame(0.85, Transaction::rsdToEur(100));
    }

    public function testGetDisplayAmountForBankTransferReturnsRawRsd(): void
    {
        $transaction = new Transaction();
        $transaction->paymentType = 1; // bank transfer = RSD
        $transaction->amount = 5000;

        self::assertSame(5000.0, $transaction->getDisplayAmount());
    }

    public function testGetDisplayAmountForNonBankTransferConvertsToEur(): void
    {
        $transaction = new Transaction();
        $transaction->paymentType = 2; // wire = EUR
        $transaction->amount = 11750;  // stored in RSD

        self::assertSame(100.0, $transaction->getDisplayAmount());
    }

    public function testGetDisplayCurrencyIsRsdForBankTransferOnly(): void
    {
        $bank = new Transaction();
        $bank->paymentType = 1;
        self::assertSame('RSD', $bank->getDisplayCurrency());

        $wire = new Transaction();
        $wire->paymentType = 2;
        self::assertSame('EUR', $wire->getDisplayCurrency());
    }

    public function testGetHrStatusReturnsLabelForKnownStatus(): void
    {
        self::assertSame('Potvrđeno', Transaction::getHrStatus(Transaction::STATUS_CONFIRMED));
        self::assertSame('Otkazano', Transaction::getHrStatus(Transaction::STATUS_CANCELLED));
    }

    public function testGetHrStatusesCoversEveryStatusConstant(): void
    {
        $statuses = Transaction::getHrStatuses();

        foreach ([
            Transaction::STATUS_NEW,
            Transaction::STATUS_WAITING_CONFIRMATION,
            Transaction::STATUS_CONFIRMED,
            Transaction::STATUS_CANCELLED,
            Transaction::STATUS_NOT_PAID,
            Transaction::STATUS_EXPIRED,
            Transaction::STATUS_PAID,
        ] as $status) {
            self::assertArrayHasKey($status, $statuses);
        }
    }
}
