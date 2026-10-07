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
        $uncountableFilter = $this->scopeToLoggedInDelegate($uncountableFilter ?? []);
        $items = $this->repo->fetchTableData($search, $filter, $offset, $limit, $order, $uncountableFilter, $idsToInclude, $idsToExclude);
        return [
            'entities' => $this->prepareEntities($items['items']),
            'countColumnData' => $items['countColumnData']
        ];
    }

    /** Same scope for the total as for the page, or a delegate's pager counts the whole network. */
    public function getTotalCount(array $uncountableFilter = [])
    {
        return parent::getTotalCount($this->scopeToLoggedInDelegate($uncountableFilter));
    }

    /** A delegate sees the beneficiaries of their schools - see BeneficiaryRepository::addDelegateScope(). */
    private function scopeToLoggedInDelegate(array $uncountableFilter): array
    {
        if ($this->getUserSession()->getLoggedInEntityType() === 'delegate') {
            $uncountableFilter[BeneficiaryRepository::DELEGATE_SCOPE] = (int) $this->getUserSession()->getLoggedInUserId();
        }

        return $uncountableFilter;
    }

    /**
     * BeneficiaryRepository::addDelegateScope() for one loaded entity: a school beneficiary
     * belongs to every delegate of the school, a school-less (MSPR) one to its createdBy.
     */
    public function isVisibleToDelegate(?BeneficiaryEntity $beneficiary, int $delegateId): bool
    {
        if (!$beneficiary) {
            return false;
        }
        if ($beneficiary->school) {
            return $beneficiary->school->hasDelegate($delegateId);
        }

        return $beneficiary->createdBy?->getId() === $delegateId;
    }

    /**
     * Who stands behind this beneficiary: every delegate of their school, or for a
     * school-less (MSPR) beneficiary the one assigned directly.
     *
     * @return \Solidarity\Delegate\Entity\Delegate[]
     */
    private function delegatesOf(BeneficiaryEntity $beneficiary): array
    {
        if ($beneficiary->school) {
            return $beneficiary->school->delegates->toArray();
        }

        return $beneficiary->createdBy ? [$beneficiary->createdBy] : [];
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
                // The school's delegates, not createdBy: for MSP whether a verified delegate
                // stands behind a beneficiary is a property of the school, and createdBy is only
                // the contact on record. Falls back to createdBy when there is no school - MSPR
                // has none, and its beneficiaries carry their delegate directly.
                'delegateVerified' => $this->hasVerifiedDelegate($beneficiary) ? 'Da' : 'Ne',
                'pm.accountNumber' => $methods,//$beneficiary->accountNumber,
                's.city' => $beneficiary->school?->city?->name,
                'status' => \Solidarity\Beneficiary\Entity\Beneficiary::getHrStatus($beneficiary->status),
                'createdBy' => implode(', ', array_map(
                    fn ($d) => sprintf('<a href="/delegate/view/id=%d">%s</a>', $d->id, htmlspecialchars($d->name)),
                    $this->delegatesOf($beneficiary)
                )),
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
     * The school owns the answer when there is one - any one of its delegates being verified
     * is enough. Only a school-less beneficiary (MSPR) falls back to the delegate assigned
     * directly to them.
     */
    private function hasVerifiedDelegate(BeneficiaryEntity $beneficiary): bool
    {
        foreach ($this->delegatesOf($beneficiary) as $delegate) {
            if ($delegate->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED) {
                return true;
            }
        }

        return false;
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
