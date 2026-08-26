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

        // Two routes to a delegate, and the school wins whenever there is one. MSP hangs the
        // delegate off the school, so choosing a school still stamps its delegate onto
        // createdBy exactly as before. MSPR has no schools — MigrateLegacyMspr sets
        // school = null — so there is nothing to derive it from and the form posts a delegate
        // directly instead.
        //
        // Resolving the school unconditionally is what made every school-less beneficiary
        // unsaveable: getById(null) returned null, ->delegate read off it gave null, and the
        // validator then refused the save with "School has no delegate assigned". Both keys
        // are always present so AbstractFactory::formatForWrite clears the relation on a
        // falsy value rather than leaving a stale one behind.
        $schoolId = !empty($postData['school']) ? $postData['school'] : null;
        $school = $schoolId ? $this->school->getById($schoolId) : null;
        $delegateId = !empty($postData['delegate']) ? (int) $postData['delegate'] : null;

        $data = [
            'id' => (isset($postData['id'])) ? $postData['id'] : null,
            'name' => trim($postData['name'] ?? ''),
            'status' => (int) ($postData['status'] ?? \Solidarity\Beneficiary\Entity\Beneficiary::STATUS_NEW),
            'comment' => trim($postData['comment'] ?? ''),
            'school' => $schoolId,
            'createdBy' => $school?->delegate?->id ?? $delegateId,
        ];

        // Parse registeredPeriods rows from form
        $registeredPeriods = [];
        $totalAmount = 0;
        if (isset($postData['registeredProjects']) && is_array($postData['registeredProjects'])) {
            foreach ($postData['registeredProjects'] as $row) {
                $rowId = (isset($row['id']) && $row['id'] !== '') ? (int) $row['id'] : null;

                // A row with no id and no period is one the user added with + and left alone:
                // nothing to preserve, and inserting it would fail on a NOT NULL period. A row
                // that *has* an id is kept even when its selects came back unusable — a delegate
                // whose assigned list no longer contains the project posts a placeholder, and
                // dropping the row here is what used to delete the registration outright. It has
                // to reach syncRegisteredPeriods(), which falls back to what is stored.
                if ($rowId === null && empty($row['period'])) {
                    continue;
                }

                $registeredPeriods[] = [
                    'id' => $rowId,
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
