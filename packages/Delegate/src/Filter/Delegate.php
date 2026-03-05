<?php

namespace Solidarity\Delegate\Filter;

use Laminas\Filter\ToInt;
use Skeletor\Core\Filter\FilterInterface;
use Turanjanin\SerbianTransliterator\Transliterator;
use Volnix\CSRF\CSRF;
use Skeletor\Core\Validator\ValidatorException;

class Delegate implements FilterInterface
{

    public function __construct(private \Solidarity\Delegate\Validator\Delegate $validator)
    {}

    public function getErrors()
    {
        return $this->validator->getMessages();
    }

    public function filter($postData): array
    {
        $int = new ToInt();
        $data = [
            'id' => (isset($postData['id'])) ? $int->filter($postData['id']) : null,
            'name' => Transliterator::toLatin($postData['name']),
            'email' => $postData['email'] ?? null,
            'phone' => $postData['phone'],
            'verifiedBy' => isset($postData['verifiedBy'])
                ? Transliterator::toLatin($postData['verifiedBy'])
                : '',
            'school' => $postData['school'],
            'comment' => Transliterator::toLatin($postData['comment'] ?? ''),
            'adminComment' => Transliterator::toLatin($postData['adminComment'] ?? ''),
            'status' => (isset($postData['status'])) ? $postData['status'] : 1,
            CSRF::TOKEN_NAME => $postData[CSRF::TOKEN_NAME],
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[CSRF::TOKEN_NAME]);

        return $data;
    }

}
