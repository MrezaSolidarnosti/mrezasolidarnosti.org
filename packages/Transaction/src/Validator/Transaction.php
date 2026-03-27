<?php

namespace Solidarity\Transaction\Validator;

use Skeletor\Core\Validator\ValidatorInterface;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Donor\Repository\DonorRepository;
use Volnix\CSRF\CSRF;

class Transaction implements ValidatorInterface
{
    private $messages = [];

    public function __construct(
        private CSRF $csrf,
        private DonorRepository $donorRepo,
        private BeneficiaryRepository $beneficiaryRepo,
    ) {
    }

    /**
     * Validates provided data, and sets errors with Flash in session.
     *
     * @param $data
     *
     * @return bool
     */
    public function isValid(array $data): bool
    {
        $valid = true;

        // @TODO verify that donor has entered more than entered amount in the form

        // Validate donor matches beneficiary payment method and project
        $donorId = $data['donor'] ?? null;
        $beneficiaryId = $data['beneficiary'] ?? null;
        $projectId = $data['project'] ?? null;

        if ($donorId && $beneficiaryId && $projectId) {
            $donor = $this->donorRepo->getById((int) $donorId);
            $beneficiary = $this->beneficiaryRepo->getById((int) $beneficiaryId);

            if ($donor && $beneficiary) {
                // Check donor has a payment method for the selected project
                $donorHasProject = false;
                $donorTypesForProject = [];
                foreach ($donor->paymentMethods as $pm) {
                    if ($pm->project->getId() === (int) $projectId) {
                        $donorHasProject = true;
                        $donorTypesForProject[] = $pm->type;
                    }
                }

                if (!$donorHasProject) {
                    $this->messages['donor'][] = 'Donor does not have a payment method for the selected project.';
                    $valid = false;
                } else {
                    // Check donor has a matching payment type with beneficiary
                    $beneficiaryTypes = [];
                    foreach ($beneficiary->paymentMethods as $bPm) {
                        $beneficiaryTypes[] = $bPm->type;
                    }

                    $matchingTypes = array_intersect($donorTypesForProject, $beneficiaryTypes);
                    if (empty($matchingTypes)) {
                        $this->messages['donor'][] = 'Donor and beneficiary have no matching payment method type for the selected project.';
                        $valid = false;
                    }
                }
            }
        }

        $accountNumber = trim($data['accountNumber'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        if ($accountNumber === '' && $instructions === '') {
            $this->messages['accountNumber'][] = 'Either account number or instructions must be entered.';
            $valid = false;
        }

        if (!$data['skipCsrf']) {
            if (!$this->csrf->validate($data)) {
                $this->messages['general'][] = 'Invalid form key.';
                $valid = false;
            }
        }


        return $valid;
    }

    /**
     * Hack used for testing
     *
     * @return string
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
