<?php
namespace Solidarity\User\Filter;

use Skeletor\Core\Filter\Str;

use Skeletor\Core\Validator\ValidatorException;
use Solidarity\User\Validator\User as UserValidator;
use Skeletor\Core\Security\Csrf;

class User extends \Skeletor\User\Filter\User
{

    protected $validator;

    public function __construct(UserValidator $validator)
    {
        parent::__construct($validator);
    }

    public function getErrors()
    {
        return $this->validator->getMessages();
    }

    public function filter(array $postData) : array
    {
        $alnum = static fn ($v) => Str::alnum((string) $v, true);
        if ((int) $postData['role'] === \Solidarity\User\Entity\User::ROLE_ADMIN) {
            $postData['delegate'] = null;
        }
        $data = [
            'id' => (isset($postData['id'])) ? $postData['id'] : null,
            'email' => $postData['email'],
            'role' => $postData['role'],
            'isActive' => (int) ($postData['isActive']),
            'displayName' => (strlen($alnum($postData['displayName'])) > 0) ? $alnum($postData['displayName']) :
                $alnum($postData['firstName'] .' '. $postData['lastName']),
            'firstName' => $alnum($postData['firstName']),
            'lastName' => $alnum($postData['lastName']),
            'delegate' => $postData['delegate'],
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME],
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}