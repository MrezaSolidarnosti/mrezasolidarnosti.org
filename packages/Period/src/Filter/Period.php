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
            // Blank and 0 both mean "use the global limit", which is what the hint under the
            // field says. Dropping it here is why an admin's value never reached the entity.
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