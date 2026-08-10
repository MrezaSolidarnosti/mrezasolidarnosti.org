<?php

namespace Solidarity\Donor\Filter;

use Volnix\CSRF\CSRF;

class DonorDonationData
{
    public function filter($postData): array
    {
        $paymentData = [];
        if(isset($postData['payment']) && is_array($postData['payment'])) {
            foreach ($postData['payment'] as $paymentId => $paymentInfo) {
                $paymentData[$paymentId] = [
                    'amount' => filter_var($paymentInfo['value'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    'currency' => filter_var($paymentInfo['currency'] ?? null, FILTER_VALIDATE_INT) ?: null,
                ];
            }
        }
        $data = [
            'donorId' => filter_var($postData['donorId'] ?? null, FILTER_VALIDATE_INT) ?: null,
            'project' => filter_var($postData['project'] ?? null, FILTER_VALIDATE_INT) ?: null,
            'paymentData' => $paymentData,
            CSRF::TOKEN_NAME => $postData[CSRF::TOKEN_NAME] ?? '',
        ];


        return $data;
    }
}