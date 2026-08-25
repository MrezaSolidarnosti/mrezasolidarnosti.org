<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Donor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Donor\Entity\PaymentMethod;

#[CoversClass(PaymentMethod::class)]
final class PaymentMethodTest extends TestCase
{
    public function testGetHrTypeReturnsLabelForKnownType(): void
    {
        self::assertSame('Bankovni transfer (lokalni)', PaymentMethod::getHrType(PaymentMethod::TYPE_BANK_TRANSFER));
        self::assertSame('Bankovni transfer (međunarodni)', PaymentMethod::getHrType(PaymentMethod::TYPE_WIRE_TRANSFER));
        self::assertSame('Western Union', PaymentMethod::getHrType(PaymentMethod::TYPE_WESTERN_UNION));
        self::assertSame('Moneygram', PaymentMethod::getHrType(PaymentMethod::TYPE_MONEYGRAM));
    }

    public function testEveryTypeConstantHasALabel(): void
    {
        $types = PaymentMethod::getHrTypes();

        foreach ([
            PaymentMethod::TYPE_BANK_TRANSFER,
            PaymentMethod::TYPE_WIRE_TRANSFER,
            PaymentMethod::TYPE_WESTERN_UNION,
            PaymentMethod::TYPE_MONEYGRAM,
        ] as $type) {
            self::assertArrayHasKey($type, $types);
        }
    }

    public function testGetCurrencyReturnsLabelForKnownCurrency(): void
    {
        self::assertSame('RSD', PaymentMethod::getCurrency(PaymentMethod::CURRENCY_RSD));
        self::assertSame('EUR', PaymentMethod::getCurrency(PaymentMethod::CURRENCY_EUR));
    }

    public function testEveryCurrencyConstantHasALabel(): void
    {
        $currencies = PaymentMethod::getCurrencies();

        self::assertArrayHasKey(PaymentMethod::CURRENCY_RSD, $currencies);
        self::assertArrayHasKey(PaymentMethod::CURRENCY_EUR, $currencies);
    }
}
