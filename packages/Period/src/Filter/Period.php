<?php

namespace Solidarity\Period\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Filter\ToInt;
use Skeletor\Core\Filter\FilterInterface;
use Volnix\CSRF\CSRF;
use Laminas\I18n\Filter\Alnum;
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
        $alnum = new Alnum(true);
        $int = new ToInt();

        $data = [
            'id' => (isset($postData['id'])) ? $int->filter($postData['id']) : null,
            'month' => $postData['month'],
            'year' => $postData['year'],
            'type' => $postData['type'],
            'active' => $postData['active'],
            'project' => $postData['project'],
            'processing' => $postData['processing'],
            // Was missing, so the "Max iznos" input on the period form was posted and then
            // silently dropped on every save. Blank means 0, not null: the column is NOT
            // NULL, and 0 is already how "no per-period override" is spelled — MigrateLegacy
            // writes it for every legacy period, and Beneficiary\Validator reads any value
            // <= 0 as "fall back to the global limit". Writing null here was a 500 on save.
            'maxAmount' => $int->filter($postData['maxAmount'] ?? 0),
            CSRF::TOKEN_NAME => $postData[CSRF::TOKEN_NAME],
        ];
//        if (!$this->validator->isValid($data)) {
//            throw new ValidatorException();
//        }
        unset($data[CSRF::TOKEN_NAME]);

        return $data;
    }

}