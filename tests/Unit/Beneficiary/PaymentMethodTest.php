<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Entity\PaymentMethod;

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
}
