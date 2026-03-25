<?php

namespace Solidarity\Beneficiary\Service;

use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\Transaction\Service\Project;

class Beneficiary extends TableView
{
    public function __construct(
        BeneficiaryRepository $repo, Session $user, Logger $logger, BeneficiaryFilter $filter,
        private Project $project, private School $school, private Delegate $delegate
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getByPeriod(int $periodId): array
    {
        return $this->repo->fetchByPeriod($periodId);
    }

    public function fetchTableData(
        $search, $filter, $offset, $limit, $order, $uncountableFilter = null, $idsToInclude = [], $idsToExclude = []
    ) {
        // delegate can only see beneficiaries added by them
        if ($this->getUserSession()->getLoggedInEntityType() === 'delegate') {
            $uncountableFilter['createdBy'] = $this->getUserSession()->getLoggedInUserId();
        }
        $items = $this->repo->fetchTableData($search, $filter, $offset, $limit, $order, $uncountableFilter, $idsToInclude, $idsToExclude);
        return [
            'count' => $items['count'],
            'entities' => $this->prepareEntities($items['items']),
            'countColumnData' => $items['countColumnData']
        ];
    }

    public function prepareEntities($entities)
    {
        $items = [];
        foreach ($entities as $beneficiary) {
            $totalAmount = 0;
            $projects = [];
            foreach ($beneficiary->registeredPeriods as $rp) {
                $totalAmount += $rp->amount;
                $projects[$rp->project->id] = $rp->project->code;
            }
            // Sum confirmed transaction amounts
            $confirmedAmount = 0;
            foreach ($beneficiary->transactions as $transaction) {
                if ($transaction->status === Transaction::STATUS_CONFIRMED) {
                    $confirmedAmount += $transaction->amount;
                }
            }
            $methods = '';
            foreach ($beneficiary->paymentMethods as $pm) {
                $methods .= PaymentMethod::getHrType($pm->type) . ', ';
                if ($pm->accountNumber) {
                    $methods .= $pm->accountNumber;
                }
                $methods .= '<br>';
            }
            $itemData = [
                'id' => $beneficiary->getId(),
                'name' =>  [
                    'value' => $beneficiary->name .' ('. implode(', ', $projects) .')',
                    'editColumn' => true,
                ],
                'rp.project' => implode(', ', $projects),
                'school' => $beneficiary->school->name,
                'sumAmount' => number_format($totalAmount, 0),
                'currentAmount' => number_format($confirmedAmount, 0),
                // TODO add message when delegate not existing
                'delegateVerified' => ($beneficiary->createdBy?->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED) ? 'Da' : 'Ne',
                'pm.accountNumber' => $methods,//$beneficiary->accountNumber,
                'status' => \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatus($beneficiary->status),
                'createdBy' => $beneficiary->createdBy?->name,
                'createdAt' => $beneficiary->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $beneficiary->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $items = [
            ['name' => 'name', 'label' => 'Ime'],
            ['name' => 'sumAmount', 'label' => 'Ukupan iznos'],
            ['name' => 'currentAmount', 'label' => 'Primljeno'],
            ['name' => 'pm.accountNumber', 'label' => 'Metode plaćanja'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatuses()],
            ['name' => 'rp.project', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'school', 'label' => 'Škola', 'filterData' => $this->school->getFilterData()]
        ];

        if ($this->getUserSession()->getLoggedInEntityType() === 'user') {
            $items[] = ['name' => 'delegateVerified', 'label' => 'Delegat verifikovan'];
            $items[] = ['name' => 'createdBy', 'label' => 'Delegat', 'filterData' => $this->delegate->getFilterData()];
        }
        $items[] = ['name' => 'createdAt', 'label' => 'Kreirano'];

        return $items;
    }
}
