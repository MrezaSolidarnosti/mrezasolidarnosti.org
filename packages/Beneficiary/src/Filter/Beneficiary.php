<?php

namespace Solidarity\Beneficiary\Filter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Skeletor\Core\Filter\FilterInterface;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Validator\Beneficiary as BeneficiaryValidator;
use Solidarity\School\Service\School;

class Beneficiary implements FilterInterface
{
    public function __construct(
        private BeneficiaryValidator $validator, private School $school    ) {
    }

    public function filter($postData): array
    {
        // todo add validation for maxAmount from project if set for registered projects when saving
        $school = $this->school->getById($postData['school']);
//        var_dump($school->delegate);
//        die();
        $data = [
            'id' => (isset($postData['id'])) ? $postData['id'] : null,
            'name' => trim($postData['name'] ?? ''),
            'status' => (int) ($postData['status'] ?? \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_NEW),
            'comment' => trim($postData['comment'] ?? ''),
            'school' => $postData['school'] ?? null,
            'createdBy' => $school->delegate?->id ?? null,
        ];

        // Parse registeredPeriods rows from form
        $registeredPeriods = [];
        $totalAmount = 0;
        if (isset($postData['registeredProjects']) && is_array($postData['registeredProjects'])) {
            foreach ($postData['registeredProjects'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                // A row with no period and no id is a blank one the user added and never
                // filled in. A row *with* an id is a stored registration and is passed on
                // even when its selects came back unusable — syncRegisteredPeriods() falls
                // back to what is stored rather than dropping it.
                if (empty($row['period']) && $id <= 0) {
                    continue;
                }
                $registeredPeriods[] = [
                    'id' => $id ?: null,
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
