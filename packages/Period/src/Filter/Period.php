<?php

namespace Solidarity\Period\Filter;

use Skeletor\Core\Filter\Str;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\Core\Security\Csrf;
use Skeletor\Core\Validator\ValidatorException;
class Period implements FilterInterface
{

//    public function __construct(private \Solidarity\Period\Validator\Period $validator)
    public function __construct()
    {
    }

    public function getErrors()
    {
        return [];
//        return $this->validator->getMessages();
    }

    public function filter($postData): array
    {
        $alnum = static fn ($v) => Str::alnum((string) $v, true);

        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'month' => $postData['month'],
            'year' => $postData['year'],
            'type' => $postData['type'],
            'active' => $postData['active'],
            'project' => $postData['project'],
            'processing' => $postData['processing'],
            // Was missing, so the "Max iznos" input on the period form was posted and then
            // silently dropped on every save. Blank means 0, not null: the column is NOT
            // NULL, and 0 is already how "no per-period override" is spelled - MigrateLegacy
            // writes it for every legacy period, and Beneficiary\Validator reads any value
            // <= 0 as "fall back to the global limit". Writing null here was a 500 on save.
            'maxAmount' => (int) ($postData['maxAmount'] ?? 0),
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME],
        ];
//        if (!$this->validator->isValid($data)) {
//            throw new ValidatorException();
//        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}