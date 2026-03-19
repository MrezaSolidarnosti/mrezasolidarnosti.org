<?php

namespace Solidarity\Beneficiary\Filter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Skeletor\Core\Filter\FilterInterface;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Validator\Beneficiary as BeneficiaryValidator;

class Beneficiary implements FilterInterface
{
    public function __construct(
        private BeneficiaryValidator $validator
    ) {
    }

    public function filter($postData): array
    {
        // todo add validation for maxAmount from project if set for registered projects when saving

        $data = [
            'id' => (isset($postData['id'])) ? $postData['id'] : null,
            'name' => trim($postData['name'] ?? ''),
            'status' => (int) ($postData['status'] ?? \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_NEW),
            'comment' => trim($postData['comment'] ?? ''),
            'school' => $postData['school'] ?? null,
            'createdBy' => $postData['createdBy'] ?? null,
        ];

        // Parse registeredPeriods rows from form
        $registeredPeriods = [];
        $totalAmount = 0;
        if (isset($postData['registeredProjects']) && is_array($postData['registeredProjects'])) {
            foreach ($postData['registeredProjects'] as $row) {
                if (empty($row['period'])) {
                    continue;
                }
                $registeredPeriods[] = [
                    'project' => (int) ($row['project']),
                    'period' => (int) $row['period'],
                    'amount' => (int) ($row['amount']),
                ];
                if ($row['amount'] > \Solidarity\Beneficiary\Entity\Beneficiary::MONTHLY_LIMIT) {

                }
                $totalAmount += $row['amount'];
            }
        }

        $data['registeredPeriods'] = $registeredPeriods;

        // Parse paymentMethods rows from form
        // JS sends: paymentMethods[idx][type], paymentMethods[idx][bankAccount], paymentMethods[idx][wireInstructions]
        $paymentMethods = [];
        if (isset($postData['paymentMethods']) && is_array($postData['paymentMethods'])) {
            foreach ($postData['paymentMethods'] as $row) {
                if (empty($row['type'])) {
                    continue;
                }
                $paymentMethods[] = [
                    'type' => (int) $row['type'],
                    'accountNumber' => trim($row['bankAccount'] ?? $row['bankAccount'] ?? ''),
                    'wireInstructions' => trim($row['wireInstructions'] ?? ''),
                ];
            }
        }

        $data['paymentMethods'] = $paymentMethods;

        if (!$this->validator->isValid($data)) {
            throw new \Skeletor\Core\Validator\ValidatorException();
        }

        return $data;
    }

    public function getErrors()
    {
        return $this->validator->getMessages();
    }
}
