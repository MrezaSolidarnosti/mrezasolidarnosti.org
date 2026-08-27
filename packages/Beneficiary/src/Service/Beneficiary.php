<?php

namespace Solidarity\Beneficiary\Service;

use Solidarity\Beneficiary\Entity\Beneficiary as BeneficiaryEntity;
use Solidarity\Beneficiary\Entity\PaymentMethod;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Filter\Beneficiary as BeneficiaryFilter;
use Solidarity\Delegate\Service\Delegate;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Entity\Transaction;
use Solidarity\School\Service\City;
use Solidarity\Transaction\Service\Project;

class Beneficiary extends TableView
{
    public function __construct(
        BeneficiaryRepository $repo, Session $user, Logger $logger, BeneficiaryFilter $filter,
        private Project $project, private School $school, private Delegate $delegate, private City $city,
        \Skeletor\Core\Activity\Service\Activity $activity) {
        parent::__construct($repo, $user, $logger, $filter, activity: $activity);
    }

    public function getByPeriod(int $periodId): array
    {
        return $this->repo->fetchByPeriod($periodId);
    }

    /** @param int|null $excludeStatus null counts everyone ever registered, removed included. */
    public function getBeneficiaryCount(?int $excludeStatus = BeneficiaryEntity::STATUS_DELETED): int
    {
        return $this->repo->getBeneficiaryCount($excludeStatus);
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
            // Money actually received: CONFIRMED and PAID. This counted CONFIRMED alone and
            // silently dropped PAID, so a beneficiary whose transactions had been marked paid
            // read lower here than on their own form — the same question, two answers.
            // Transaction::getRealisedStatuses() is the single definition both now use.
            $confirmedAmount = 0;
            foreach ($beneficiary->transactions as $transaction) {
                if (in_array($transaction->status, Transaction::getRealisedStatuses(), true)) {
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
                // Null-safe like the city below it: a school is optional now that MSPR
                // beneficiaries are assigned a delegate directly, and reading ->name off
                // null warned on every row of the listing.
                'school' => $beneficiary->school?->name,
                'sumAmount' => number_format($totalAmount, 0),
                'currentAmount' => number_format($confirmedAmount, 0),
                // The school's delegate, not createdBy. The column asks whether this
                // beneficiary has a verified delegate behind them, and for MSP that is a
                // property of the school — reading createdBy reported "Ne" for a school with a
                // perfectly good verified delegate whenever the beneficiary had been orphaned
                // (Delegate::update() nullifies createdBy across the delegate whenever any
                // school leaves their list), which says nothing about whether a delegate exists.
                //
                // Falls back to createdBy when there is no school: MSPR has none, and its
                // beneficiaries carry their delegate directly. Same precedence the save path
                // uses in Beneficiary\Filter, so the column agrees with what the form stores.
                'delegateVerified' => $this->hasVerifiedDelegate($beneficiary) ? 'Da' : 'Ne',
                'pm.accountNumber' => $methods,//$beneficiary->accountNumber,
                's.city' => $beneficiary->school?->city?->name,
                'status' => \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatus($beneficiary->status),
                'createdBy' => sprintf('<a href="/delegate/view/id=%d">%s</a>', $beneficiary->createdBy?->id, $beneficiary->createdBy?->name),
                'createdAt' => $beneficiary->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $beneficiary->getId(),
            ];
        }
        return $items;
    }

    /**
     * Is there a verified delegate standing behind this beneficiary?
     *
     * The school owns the answer when there is one — that is what the MSP delegate structure
     * means, and it stays true even if the beneficiary's own createdBy has been nulled. Only a
     * school-less beneficiary (MSPR) falls back to the delegate assigned directly to them.
     */
    private function hasVerifiedDelegate(BeneficiaryEntity $beneficiary): bool
    {
        $delegate = $beneficiary->school?->delegate ?? $beneficiary->createdBy;

        return $delegate?->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED;
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
            ['name' => 'school', 'label' => 'Škola', 'filterData' => $this->school->getFilterData()],
            ['name' => 's.city', 'label' => 'Grad', 'filterData' => $this->city->getFilterData()]
        ];

        if ($this->getUserSession()->getLoggedInEntityType() === 'user') {
            $items[] = ['name' => 'delegateVerified', 'label' => 'Delegat postoji <br /> i verifikovan'];
            $items[] = ['name' => 'createdBy', 'label' => 'Delegat', 'filterData' => $this->delegate->getFilterData()];
        }
        $items[] = ['name' => 'createdAt', 'label' => 'Kreirano'];

        return $items;
    }
}
