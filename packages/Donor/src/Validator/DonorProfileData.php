<?php

namespace Solidarity\Donor\Validator;

use Skeletor\Core\Security\Csrf;

class DonorProfileData
{

    private array $messages = [];
    public function __construct(private Csrf $csrf)
    {

    }

    public function isValid(array $data): bool
    {
        $valid = true;
        $this->messages = [];
        if(trim($data['firstName']) === '') {
            $this->messages['firstName'][] = 'First name is required';
            $valid = false;
        }

        if(strlen(trim($data['firstName'])) < 2) {
            $this->messages['firstName'][] = 'First name must be at least 2 characters long';
            $valid = false;
        }

        if(trim($data['lastName']) === '') {
            $this->messages['lastName'][] = 'Last name is required';
            $valid = false;
        }

        if(strlen(trim($data['lastName'])) < 2) {
            $this->messages['lastName'][] = 'Last name must be at least 2 characters long';
            $valid = false;
        }

        if (!$this->csrf->validate($data)) {
            $this->messages['general'][] = 'Expired, please try again.';
            $valid = false;
        }

        return $valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}