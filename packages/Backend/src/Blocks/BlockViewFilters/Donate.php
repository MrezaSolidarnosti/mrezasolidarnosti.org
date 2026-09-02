<?php

namespace Solidarity\Backend\Blocks\BlockViewFilters;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Frontend\Service\Session;
use Solidarity\Transaction\Service\Transaction;

class Donate implements BlockViewFilterInterface
{
    public function __construct(
        protected Session $session, private Transaction $transaction
    )
    {

    }

    public function filter(array $data): array
    {
        $data['existingProjectId'] = null;
        $data['existingPaymentMethods'] = [];
        $data['hasUnmetNeeds'] = false;
        if (!$this->session->isDonor()) {
            return $data;
        }

        $donor = $this->session->getUser();
        if (!$donor) {
            return $data;
        }
        $data['hasUnmetNeeds'] = $this->transaction->hasUnmetNeeds($donor);

        $paymentMethodsByProject = [];
        foreach ($donor->paymentMethods as $paymentMethod) {
            $paymentMethodsByProject[$paymentMethod->project->getId()][] = $paymentMethod;
        }

        $projectIds = array_keys($paymentMethodsByProject);
        if (count($projectIds) === 0) {
            return $data;
        }

        $data['existingProjectId'] = count($projectIds) === 1 ? $projectIds[0] : -1;
        $representativeProjectId = $projectIds[0];

        foreach ($paymentMethodsByProject[$representativeProjectId] as $paymentMethod) {
            $data['existingPaymentMethods'][] = [
                'type' => $paymentMethod->type,
                'amount' => $paymentMethod->amount,
                'currency' => $paymentMethod->currency,
            ];
        }

        $data['existingProjectName'] = $data['existingProjectId'] === -1
            ? 'Oba pravca podrške'
            : $paymentMethodsByProject[$representativeProjectId][0]->project->name;

        return $data;
    }
}
