<?php

namespace Solidarity\Delegate\Validator;

use Laminas\Validator\EmailAddress;
use Skeletor\Core\Validator\ValidatorInterface;
use Skeletor\Core\Security\Csrf;

/**
 * Class Client.
 * User validator.
 *
 * @package Fakture\Client\Validator
 */
class Delegate implements ValidatorInterface
{

    /**
     * @var Csrf
     */
    private $csrf;

    private $delegateRepository;

    private $messages = [];

    /**
     * User constructor.
     *
     * @param Csrf $csrf
     */
    public function __construct(
        Csrf $csrf,
        \Solidarity\Delegate\Repository\DelegateRepository $delegateRepository
    ) {
        $this->csrf               = $csrf;
        $this->delegateRepository = $delegateRepository;
    }

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
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->messages['general'][] = 'Uneta email adresa nije ispravna.' . $data['email'];
            $valid = false;
        }

        $schoolIds = array_filter(array_map('intval', $data['schools'] ?? []));
        if (!empty($schoolIds)) {
            // Check for duplicate school selections
            if (count($schoolIds) !== count(array_unique($schoolIds))) {
                $this->messages['schools'][] = 'Ista škola ne može biti izabrana više puta.';
                $valid = false;
            }

            // No "already assigned to another delegate" check: a school may have several
            // delegates, all at the same level.
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
}
