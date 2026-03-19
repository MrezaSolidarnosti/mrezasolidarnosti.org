<?php

namespace Solidarity\Beneficiary\Service;

use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Delegate\Entity\Delegate;
use Solidarity\Transaction\Service\Project;

class Beneficiary extends TableView
{
    public function __construct(
        BeneficiaryRepository $repo, Session $user, Logger $logger, BeneficiaryFilter $filter,
        private Project $project
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function fetchTableData(
        $search, $filter, $offset, $limit, $order, $uncountableFilter = null, $idsToInclude = [], $idsToExclude = []
    ) {
        // delegate can only see own account
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
        // todo add total received (confirmed) amount
        foreach ($entities as $beneficiary) {
            $totalAmount = 0;
            $projects = [];
            foreach ($beneficiary->registeredPeriods as $rp) {
                $totalAmount += $rp->amount;
                $projects[$rp->project->id] = $rp->project->code;
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
                    'value' => $beneficiary->name,
                    'editColumn' => true,
                ],
                'pm.project' => implode(', ', $projects),
                'sumAmount' => number_format($totalAmount, 0),
                // TODO add message when delegate not existing
                'delegateVerified' => ($beneficiary->createdBy?->status === Delegate::STATUS_VERIFIED) ? 'Da' : 'Ne',
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
        return [
            ['name' => 'name', 'label' => 'Ime'],
//            ['name' => 'amount', 'label' => 'Trenutni iznos'],
            ['name' => 'pm.project', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'sumAmount', 'label' => 'Ukupan iznos'],
            ['name' => 'pm.accountNumber', 'label' => 'Metode plaćanja'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatuses()],
            ['name' => 'delegateVerified', 'label' => 'Delegat verifikovan'],
            ['name' => 'createdBy', 'label' => 'Delegat'],
            ['name' => 'createdAt', 'label' => 'Kreirano'],
        ];
    }
}
