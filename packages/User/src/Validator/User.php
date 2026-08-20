<?php

namespace Solidarity\User\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Validator\InvalidFormTokenException;
use Skeletor\Core\Validator\ValidatorInterface;
use Skeletor\User\Repository\UserRepository;
use Skeletor\Core\Security\Csrf;

class User extends \Skeletor\User\Validator\User implements ValidatorInterface
{
    private $messages = [];


    public function __construct(private EntityManagerInterface $entityManager, private Csrf $csrf)
    {
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
        if (!$this->csrf->validate($data)) {
            throw new InvalidFormTokenException();
        }
        $valid = true;
        // @TODO temporary disabled. find easy solution to use user Validator to change rules pre app.

//        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
//            $this->messages['email'][] = 'Email you entered is not valid.';
//            $valid = false;
//        }

//        if ((int) $data['id'] === 0 && $this->userRepo->emailExists($data['email'])) {
//            $this->messages['email'][] = 'Email you entered already exists in system.';
//            $valid = false;
//        }

        if (strlen($data['displayName']) < 2) {
            $this->messages['displayName'][] = 'Display name must be at least 2 characters long.';
            $valid = false;
        }
        if ((int) $data['role'] === 0) {
            $this->messages['role'][] = 'Invalid role selected.';
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
