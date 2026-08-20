<?php

namespace Solidarity\Delegate\Filter;

use Skeletor\Core\Filter\FilterInterface;
use Turanjanin\SerbianTransliterator\Transliterator;
use Skeletor\Core\Security\Csrf;
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
        // todo if delegate is MSP it needs school relation

        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'name' => Transliterator::toLatin($postData['name']),
            'email' => $postData['email'] ?? null,
            'phone' => $postData['phone'],
            'verifiedBy' => isset($postData['verifiedBy'])
                ? Transliterator::toLatin($postData['verifiedBy'])
                : '',
            'schools' => $postData['schools'] ?? [],
            'projects' => $postData['projects'],
            'comment' => Transliterator::toLatin($postData['comment'] ?? ''),
            'adminComment' => Transliterator::toLatin($postData['adminComment'] ?? ''),
            'status' => (isset($postData['status'])) ? $postData['status'] : 1,
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME],
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}
