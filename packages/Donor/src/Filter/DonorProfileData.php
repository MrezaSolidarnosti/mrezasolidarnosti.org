<?php

namespace Solidarity\Donor\Filter;

use Skeletor\Core\Validator\ValidatorException;
use Skeletor\Core\Security\Csrf;

class DonorProfileData
{
    public function __construct()
    {
    }

    public function filter($postData): array
    {
        $data = [
            'id' => filter_var($postData['id'] ?? null, FILTER_VALIDATE_INT) ?: null,
            'email' => trim($postData['email'] ?? ''),
            'firstName' => trim($postData['firstName'] ?? ''),
            'lastName' => trim($postData['lastName'] ?? ''),
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME] ?? '',
        ];

        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }
}