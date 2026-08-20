<?php

namespace Solidarity\Donor\Filter;

use Skeletor\Core\Filter\Str;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\User\Service\Session;
use Skeletor\Core\Security\Csrf;
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
        $alnum = static fn ($v) => Str::alnum((string) $v, true);

        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'email' => $postData['email'],
            'firstName' => $postData['firstName'],
            'lastName' => $postData['lastName'],
            'wantsToDonateTo' => $postData['wantsToDonateTo'],
            'comment' => $postData['comment'],
            'isActive' => $postData['isActive'],
            'projects' => $postData['projects'],
            'status' => (isset($postData['status'])) ? $postData['status'] : 1,
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME],
        ];

        // Parse paymentMethods rows from form
        // JS sends: paymentMethods[idx][project], paymentMethods[idx][paymentType],
        //           paymentMethods[idx][monthly], paymentMethods[idx][amount], paymentMethods[idx][currency]
        $paymentMethods = [];
        if (isset($postData['paymentMethods']) && is_array($postData['paymentMethods'])) {
            foreach ($postData['paymentMethods'] as $row) {
                if (empty($row['paymentType']) || $row['paymentType'] === '-1') {
                    continue;
                }
                if (empty($row['project']) || $row['project'] === '-1') {
                    continue;
                }
                $paymentMethods[] = [
                    'project' => (int) $row['project'],
                    'type' => (int) $row['paymentType'],
                    'monthly' => (int) ($row['monthly'] ?? 0),
                    'amount' => (int) ($row['amount'] ?? 0),
                    'currency' => (int) ($row['currency'] ?? \Solidarity\Donor\Entity\PaymentMethod::CURRENCY_RSD),
                ];
            }
        }
        $data['paymentMethods'] = $paymentMethods;

        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}