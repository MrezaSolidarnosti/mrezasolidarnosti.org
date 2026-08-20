<?php

namespace Solidarity\Donor\Validator;

use Solidarity\Donor\Entity\PaymentMethod;
use Skeletor\Core\Security\Csrf;

class DonorDonationData
{
    private array $messages = [];
    public function __construct(private Csrf $csrf)
    {

    }

    public function isValid(array $data): bool
    {
        $valid = true;
        $this->messages = [];

        if (!$this->csrf->validate($data)) {
            $this->messages['general'][] = 'Expired, please try again.';
            $valid = false;
        }

        if($data['project'] !== -1 && $data['project'] !== 1 && $data['project'] !== 2) {
            $this->messages['project'][] = 'Project does not exist.';
            $valid = false;
        }

        if(isset($data['paymentData']) && count($data['paymentData']) > 0) {
            foreach($data['paymentData'] as $paymentType => $paymentData) {
                if(!array_key_exists($paymentType, PaymentMethod::getHrTypes())) {
                    $this->messages['paymentType'][] = 'Payment type not supported.';
                    $valid = false;
                }
                if(!$paymentData['amount']) {
                    $this->messages['paymentAmount'][] = 'Payment amount is required.';
                    $valid = false;
                }
                if(!array_key_exists($paymentData['currency'], PaymentMethod::getCurrencies())) {
                    $this->messages['paymentType'][] = 'Currency not supported.';
                    $valid = false;
                }
                if($paymentData['currency'] === PaymentMethod::CURRENCY_RSD && $paymentData['amount'] < 500) {
                    $this->messages['paymentType'][] = 'Minimum amount for RSD is 500.';
                    $valid = false;
                }
                if($paymentData['currency'] === PaymentMethod::CURRENCY_EUR && $paymentData['amount'] < 10) {
                    $this->messages['paymentType'][] = 'Minimum amount for EUR is 10.';
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}