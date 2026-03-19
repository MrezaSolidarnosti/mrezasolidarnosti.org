<?php

namespace Solidarity\Beneficiary\Validator;

use Skeletor\Core\Validator\ValidatorInterface;

class Beneficiary implements ValidatorInterface
{
    private array $messages = [];

    public function isValid(array $data): bool
    {
        $this->messages = [];
        if (empty($data['name'])) {
            $this->messages['name'][] = 'Ime je neophodno.';
        }

        if (empty($data['paymentMethods'])) {
            $this->messages['paymentMethods'][] = 'Bar jedan metod plaćanja mora biti unet.';
        } else {
            foreach ($data['paymentMethods'] as $index => $row) {
                // Budget of the Republic of Serbia
                if (str_starts_with($row['accountNumber'], '840')) {
                    $this->messages['paymentMethods'][] = 'Broj računa pripada budzetu Republike Srbije.';
                }
                // Eurobank Direktna
                if (str_starts_with($row['accountNumber'], '150')) {
                    $this->messages['paymentMethods'][] = 'Broj računa pripada banci "Eurobank Direktna" koja više ne postoji.';
                }
                // MTS Bank
                if (str_starts_with($row['accountNumber'], '360')) {
                    $this->messages['paymentMethods'][] = 'Broj računa pripada banci "MTS Bank" koja više ne postoji.';
                }
                if (!$this->validateAccountNumber($row['accountNumber'])) {
                    $this->messages['paymentMethods'][] = 'Broj računa nije validan, kontrolni broj je pogrešan.';
                }
            }
        }

        if (empty($data['registeredPeriods'])) {
            $this->messages['registeredPeriods'][] = 'Bar jedan period mora biti unet.';
        } else {
            // todo might need to fetch period data, to determine limit, for half periods, limit should be halved
            foreach ($data['registeredPeriods'] as $index => $row) {
                if (empty($row['period'])) {
                    $this->messages['registeredPeriods'][] = sprintf('Period je neophodan za red %d.', $index + 1);
                }
                if (!isset($row['amount']) || $row['amount'] <= 0) {
                    $this->messages['registeredPeriods'][] = sprintf('Iznos mora biti veći od nule za red %d.', $index + 1);
                } elseif ($row['amount'] > \Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT) {
                    $this->messages['registeredPeriods'][] = sprintf(
                        'Iznos u redu %d je veći od limita od %s.',
                        $index + 1,
                        number_format(\Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT, 0)
                    );
                }
            }
        }

        return empty($this->messages);
    }

    private function validateAccountNumber(string $accountNumber): bool
    {
        $controlNumber = $this->mod97(substr($accountNumber, 0, -2));

        return str_pad($controlNumber, 2, '0', STR_PAD_LEFT) === substr($accountNumber, -2);
    }

    private function mod97(string $accountNumber, int $base = 100): int
    {
        $controlNumber = 0;

        for ($x = strlen($accountNumber) - 1; $x >= 0; --$x) {
            $num = (int) $accountNumber[$x];
            $controlNumber = ($controlNumber + ($base * $num)) % 97;
            $base = ($base * 10) % 97;
        }

        return 98 - $controlNumber;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
