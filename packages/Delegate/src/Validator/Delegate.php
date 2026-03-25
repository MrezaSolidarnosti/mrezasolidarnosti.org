<?php

namespace Solidarity\Delegate\Validator;

use Laminas\Validator\EmailAddress;
use Skeletor\Core\Validator\ValidatorInterface;
use Volnix\CSRF\CSRF;

/**
 * Class Client.
 * User validator.
 *
 * @package Fakture\Client\Validator
 */
class Delegate implements ValidatorInterface
{

    /**
     * @var CSRF
     */
    private $csrf;

    private $delegateRepository;

    private $schoolRepository;

    private $messages = [];

    /**
     * User constructor.
     *
     * @param CSRF $csrf
     */
    public function __construct(
        CSRF $csrf,
        \Solidarity\Delegate\Repository\DelegateRepository $delegateRepository,
        \Solidarity\School\Repository\SchoolRepository $schoolRepository
    ) {
        $this->csrf               = $csrf;
        $this->delegateRepository = $delegateRepository;
        $this->schoolRepository   = $schoolRepository;
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

            // Check that each school is not already assigned to another delegate
            $currentDelegateId = isset($data['id']) ? (int) $data['id'] : null;
            foreach (array_unique($schoolIds) as $schoolId) {
                $school = $this->schoolRepository->getById($schoolId);
                if ($school && $school->delegate && $school->delegate->getId() !== $currentDelegateId) {
                    $this->messages['schools'][] = sprintf(
                        'Škola "%s" je već dodeljena drugom delegatu.',
                        $school->name
                    );
                    $valid = false;
                }
            }
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
