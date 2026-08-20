<?php

namespace Solidarity\Transaction\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\User\Service\Session;
use Skeletor\Core\Security\Csrf;
use Skeletor\Core\Validator\ValidatorException;
class Transaction implements FilterInterface
{

    public function __construct(private \Solidarity\Transaction\Validator\Transaction $validator)
    {
    }

    public function getErrors()
    {
        return $this->validator->getMessages();
    }

    public function filter($postData): array
    {

        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'beneficiary' => $postData['beneficiary'] ?? null,
            'project' => $postData['project'] ?? null,
            'period' => $postData['period'] ?? null,
            'amount' => (int) ($postData['amount'] ?? 0),
            'amountEur' => (int) ($postData['amountEur'] ?? 0),
            'comment' => $postData['comment'] ?? '',
            'status' => (int) ($postData['status'] ?? 1),
            'donor' => $postData['donor'],
            'paymentType' => $postData['paymentType'] ?? null,
            'accountNumber' => $postData['accountNumber'] ?? null,
            'instructions' => $postData['instructions'] ?? null,
            // Donor-initiated one-time instruction vs. cron-allocated monthly pledge.
            'manual' => (bool) ($postData['manual'] ?? false),
            'skipCsrf' => $postData['skipCsrf'] ?? false,
            // Allocator-generated transactions already matched donor↔beneficiary by the
            // donor's *chosen* types (on-demand) or pledged methods (cron); the validator's
            // persisted-payment-method check doesn't apply to them.
            'skipDonorPaymentCheck' => $postData['skipDonorPaymentCheck'] ?? false,
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME] ?? null,
        ];
        if (!$this->validator->isValid($data)) {
            throw new ValidatorException();
        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}
