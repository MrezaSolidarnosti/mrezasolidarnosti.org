<?php

namespace Solidarity\Educator\Validator;

use Skeletor\Core\Validator\ValidatorInterface;
use Solidarity\Educator\Repository\EducatorRepository;
use Volnix\CSRF\CSRF;

/**
 * Class Client.
 * User validator.
 *
 * @package Fakture\Client\Validator
 */
class Educator implements ValidatorInterface
{
    private $messages = [];

    /**
     * User constructor.
     *
     * @param CSRF $csrf
     */
    public function __construct(private CSRF $csrf, private EducatorRepository $educatorRepo)
    {}

    /**
     * Validates provided data, and sets errors with Flash in session.
     *
     * @param $data
     *
     * @return bool
     */
    public function isValid(array $data): bool
    {
        $valid = true;
        $this->messages = [];
        if (!$this->validateAccountNumber($data['accountNumber'])) {
            $this->messages['accountNumber'][] = 'Uneti broj žiro računa nije ispravan.';
            $valid = false;
        }
        $existing = $this->educatorRepo->fetchAll(['accountNumber' => $data['accountNumber'], 'period' => $data['period']]);
        if ($existing) {
            foreach ($existing as $educator) {
                if ($educator->id !== $data['id']) {
                    $this->messages['accountNumber'][] = 'Uneti broj žiro računa je već korišćen za ovaj period.';
                    $valid = false;
                }
            }
        }
        if ($data['amount'] > \Solidarity\Educator\Entity\Educator::MONTHLY_LIMIT) {
            $this->messages['amount'][] = 'Uneti iznos je veći od dozvoljenog : ' . \Solidarity\Educator\Entity\Educator::MONTHLY_LIMIT;
            $valid = false;
        }
        if (!$this->csrf->validate($data)) {
            $this->messages['general'][] = 'Stranica je istekla, probajte ponovo.';
            $valid = false;
        }

        return $valid;
    }

    /**
     * Hack used for testing
     *
     * @return string
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param string $accountNumber
     *
     * @return bool
     */
    private function validateAccountNumber(string $accountNumber): bool
    {
        $controlNumber = $this->mod97(substr($accountNumber, 0, -2));

        return str_pad($controlNumber, 2, '0', STR_PAD_LEFT) === substr($accountNumber, -2);
    }

    /**
     * @param string $accountNumber
     * @param int    $base
     *
     * @return int
     */
    private function mod97(string $accountNumber, int $base = 100) : int
    {
        $controlNumber = 0;
        for ($x = strlen($accountNumber) - 1; $x >= 0; --$x) {
            $num = (int) $accountNumber[$x];
            $controlNumber = ($controlNumber + ($base * $num)) % 97;
            $base = ($base * 10) % 97;
        }

        return 98 - $controlNumber;
    }

    // @TODO test this, might have exact validation
    function mod97_2($accountNumber) {
        $form_input = $accountNumber;
        $snum = "";
        $sstr = strtoupper($form_input);

        for ($x = 0; $x < strlen($sstr); $x++) {
            $c = ord($sstr[$x]); // Get the ASCII value of the character

            if ($c >= 65 && $c <= 90) { // A-Z
                $snum .= ($c - 55);
            } elseif ($c >= 48 && $c <= 57) { // 0-9
                $snum .= ($c - 48);
            } elseif ($c != 45 && $c != 32) { // Not '-' or 'space'
                return 0;
//                echo "Pogresan karakter " . $sstr[$x] . " na poziciji " . ($x + 1);
            }
        }

//        return substr($snum, -2);
        return $snum;
    }
}
