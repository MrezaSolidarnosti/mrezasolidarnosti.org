<?php

namespace Solidarity\Donor\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Filter\ToInt;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\User\Service\Session;
use Volnix\CSRF\CSRF;
use Laminas\I18n\Filter\Alnum;
use Skeletor\Core\Validator\ValidatorException;
class Donor implements FilterInterface
{

    public function __construct(private \Solidarity\Donor\Validator\Donor $validator)
    {
    }

    public function getErrors()
    {
        return $this->validator->getMessages();
    }

    public function filter($postData): array
    {
        $alnum = new Alnum(true);
        $int = new ToInt();

        //$postData['paymentMethods']
        $data = [
            'id' => (isset($postData['id'])) ? $int->filter($postData['id']) : null,
            'email' => $postData['email'],
            'firstName' => $postData['firstName'],
            'lastName' => $postData['lastName'],
            'wantsToDonateTo' => $postData['wantsToDonateTo'],
            'comment' => $postData['comment'],
            'isActive' => $postData['isActive'],
            'projects' => $postData['projects'],
            'status' => (isset($postData['status'])) ? $postData['status'] : 1,
//            CSRF::TOKEN_NAME => $postData[CSRF::TOKEN_NAME],
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[CSRF::TOKEN_NAME]);

        return $data;
    }

}