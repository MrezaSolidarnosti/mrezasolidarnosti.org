<?php

namespace Solidarity\Backend\Blocks\Donate;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Frontend\Service\Session;

class DonateViewFilter implements BlockViewFilterInterface
{
    public function __construct(
        protected Session $session
    )
    {

    }

    public function filter(array $data): array
    {
        $data['existingProjectId'] = null;
        $data['existingFrequency'] = null;
        $data['existingPaymentMethods'] = [];

        if (!$this->session->isDonor()) {
            return $data;
        }

        $donor = $this->session->getUser();
        if (!$donor) {
            return $data;
        }

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
            if ($data['existingFrequency'] === null) {
                $data['existingFrequency'] = $paymentMethod->monthly;
            }
        }

        $data['existingProjectName'] = '';
        foreach ($donor->projects as $project) {
            $data['existingProjectName'] = $project->name;
        }
        if (count($donor->projects) > 1) {
            $data['existingProjectName'] = 'Oba pravca podrške';
        }

        return $data;
    }
}
