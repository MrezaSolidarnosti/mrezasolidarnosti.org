<?php

declare(strict_types=1);

namespace Solidarity\Tests\Unit\Beneficiary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solidarity\Beneficiary\Service\BankAccountSeedGenerator;

#[CoversClass(BankAccountSeedGenerator::class)]
final class BankAccountSeedGeneratorTest extends TestCase
{
    #[DataProvider('mod97Provider')]
    public function testMod97ComputesControlNumber(string $input, int $expected): void
    {
        self::assertSame($expected, BankAccountSeedGenerator::mod97($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function mod97Provider(): array
    {
        return [
            'empty string'   => ['', 98],   // no digits -> control 0 -> 98
            'single zero'    => ['0', 98],
            'single one'     => ['1', 95],   // (100*1)%97 = 3 -> 98-3
            'sixteen zeros'  => ['0000000000000000', 98],
        ];
    }
}
