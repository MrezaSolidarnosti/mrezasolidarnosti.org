<?php
namespace Solidarity\Donor\Service;

use Solidarity\Donor\Repository\DonorRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Donor\Entity\PaymentMethod;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project;
use Tamtamchik\SimpleFlash\Flash;

class Donor extends TableView
{

    /**
     * @param DonorRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        DonorRepository $repo, Session $user, Logger $logger, DonorFilter $filter, private \DateTime $dt,
        private Mailer $mailer, private Project $project
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getDonorsByProject($project)
    {
        return $this->repo->getDonorsByProject($project);
    }

    public function create(array $data)
    {
        $entity = $this->getEntities(['email' => $data['email']]);
        if (count($entity)) {
            $data['id'] = $entity[0]->id;
            $entity = parent::update($data);
        } else {
            $entity = parent::create($data);
        }
        //@TODO
//        $this->mailer->sendDonorRegisteredMail($entity->email);

        return $entity;
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Donor\Entity\Donor $donor */
        foreach ($entities as $donor) {
            $projects = [];
            foreach ($donor->projects as $project) {
                $projects[] = $project->code;
            }

            // Pledged amounts per project (from payment methods, converted to RSD)
            $pledgedByProject = [];
            foreach ($donor->paymentMethods as $pm) {
                $code = $pm->project->code;
                $amount = $pm->type !== PaymentMethod::TYPE_BANK_TRANSFER
                    ? Transaction::eurToRsd($pm->amount)
                    : $pm->amount;
                $pledgedByProject[$code] = ($pledgedByProject[$code] ?? 0) + $amount;
            }
            $pledgedParts = [];
            foreach ($pledgedByProject as $code => $amount) {
                $pledgedParts[] = $code . ' (' . number_format($amount, 0, '.', ',') . ')';
            }

            // Confirmed/paid amounts per project (from transactions)
            $paidByProject = [];
            foreach ($donor->transactions as $transaction) {
                if ($transaction->status === Transaction::STATUS_CONFIRMED || $transaction->status === Transaction::STATUS_PAID) {
                    $code = $transaction->project->code;
                    $paidByProject[$code] = ($paidByProject[$code] ?? 0) + $transaction->amount;
                }
            }
            $paidParts = [];
            foreach ($paidByProject as $code => $amount) {
                $paidParts[] = $code . ' (' . number_format($amount, 0, '.', ',') . ')';
            }

            // Payment methods display
            $methods = '';
            foreach ($donor->paymentMethods as $pm) {
                $methods .= PaymentMethod::getHrType($pm->type) . ' - '
                    . number_format($pm->amount, 0, '.', ',') . ' '
                    . PaymentMethod::getCurrency($pm->currency)
                    . '<br>';
            }

            $itemData = [
                'id' => $donor->getId(),
                'email' =>  [
                    'value' => $donor->email .' ('. implode(', ', $projects) . ')',
                    'editColumn' => true,
                ],
                'p.id' => implode(', ', $projects),
                'pledgedAmount' => implode(' | ', $pledgedParts),
                'paidAmount' => implode(' | ', $paidParts),
                'paymentMethods' => $methods,
                'status' => \Solidarity\Donor\Entity\Donor::getHrStatus($donor->status),
                'isActive' => ($donor->isActive) ? 'Da': 'Ne',
                'createdAt' => $donor->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $donor->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'p.id', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'pledgedAmount', 'label' => 'Obećano'],
            ['name' => 'paidAmount', 'label' => 'Uplaćeno'],
            ['name' => 'paymentMethods', 'label' => 'Način uplate'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Donor\Entity\Donor::getHrStatuses()],
            ['name' => 'isActive', 'label' => 'Aktivan', 'filterData' => [0 => 'No', 1 => 'Yes']],
            ['name' => 'createdAt', 'label' => 'Registrovan'],
        ];

        return $columnDefinitions;
    }

}