<?php

namespace Solidarity\Beneficiary\Service;

class BankAccountSeedGenerator
{
    const BANK_CODES = [160, 170, 205, 265, 275, 310, 325, 340];
    const BANK_NAMES = [160=>"Banca Intesa", 170=>"UniCredit", 205=>"Komercijalna", 265=>"Raiffeisen", 275=>"OTP", 310=>"NLB", 325=>"Vojvođanska", 340=>"Erste"];

    public static function mod97(string $accountNumber, int $base = 100): int
    {
        $controlNumber = 0;
        for ($x = strlen($accountNumber) - 1; $x >= 0; --$x) {
            $num = (int) $accountNumber[$x];
            $controlNumber = ($controlNumber + ($base * $num)) % 97;
            $base = ($base * 10) % 97;
        }
        return 98 - $controlNumber;
    }

    public static function generateNumbers($count) {
        for ($i = 0; $i < $count-1; $i++) {
            $bank = static::BANK_NAMES[array_rand(static::BANK_CODES)];
            // Generate random 13-digit number
            $middle = str_pad((string)random_int(1000000000000, 9999999999999), 13, "0", STR_PAD_LEFT);
            $first16 = $bank . $middle;
            $control = mod97($first16);
            $controlStr = str_pad((string)$control, 2, "0", STR_PAD_LEFT);
            $full = $first16 . $controlStr;

            echo sprintf("%s  (%s)\n", $full, static::BANK_NAMES[$bank]);
        }
    }

}