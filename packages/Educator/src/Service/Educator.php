<?php
namespace Solidarity\Educator\Service;

use Solidarity\Delegate\Service\Delegate;
use Solidarity\Educator\Repository\EducatorRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Educator\Filter\Educator as EducatorFilter;
use Solidarity\Educator\Repository\PeriodRepository;
use Solidarity\School\Service\School;
use Solidarity\Transaction\Service\Round;
use Tamtamchik\SimpleFlash\Flash;

class Educator extends TableView
{

    /**
     * @param EducatorRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        EducatorRepository $repo, Session $user, Logger $logger, EducatorFilter $filter, private \DateTime $dt,
        private Period $period, private School $school, private Delegate $delegate
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function fetchTableData(
        $search, $filter, $offset, $limit, $order, $uncountableFilter = null, $idsToInclude = [], $idsToExclude = []
    ) {
        // delegate can only see educators from assigned school
        if ($this->getUserSession()->getLoggedInEntityType() === 'delegate') {
//            $delegate = $this->delegate->getById($this->getUserSession()->getLoggedInUserId());
//            $uncountableFilter['school'] = $delegate->school->id;
        }

        $items = $this->repo->fetchTableData($search, $filter, $offset, $limit, $order, $uncountableFilter, $idsToInclude, $idsToExclude);
        return [
            'count' => $items['count'],
            'entities' => $this->prepareEntities($items['items']),
            'countColumnData' => $items['countColumnData']
        ];
    }

    /**
     * Make sure if existing accNumber/name combo is entered for creation, to just update amount, and reset status
     *
     * @param array $data
     * @return mixed
     */
//    public function create(array $data)
//    {
////        $round = $this->round->getActiveRound();
//        $entity = $this->getEntities(['accountNumber' => $data['accountNumber'], 'name' => $data['name']]);
//
//        if (count($entity)) {
//            $data['id'] = $entity[0]->id;
//            $data['status'] = \Solidarity\Educator\Entity\Educator::STATUS_NEW;
//            $data['schoolName'] = $entity[0]->schoolName;
//            $data['city'] = $entity[0]->city;
//            $data['comment'] = $entity[0]->comment;
//            $data['slipLink'] = $entity[0]->slipLink;
//            /* @var \Solidarity\Educator\Entity\Educator $entity */
//            $entity = parent::update($data);
//        } else {
//            $entity = parent::create($data);
//        }
//        return $entity;
//    }

    public function setRoundAmount($educator, $round, $amount)
    {
        $this->repo->setRoundAmount($educator, $round, $amount);
    }

    public function getEntityData($id)
    {
        $educator = $this->getById($id);
//        $delegate = $this->delegate->getEntities(['schoolName' =>$educator->schoolName, 'city' => $educator->city])[0];
        $data = parent::getEntityData($id);
//        $data['delegateVerified'] = ($delegate->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED) ? 1:0;

        return $data;
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Educator\Entity\Educator $educator */
        foreach ($entities as $educator) {
            // @TODO make sure all educators have delegate
            $delegateVerified = 'No';
            if ($educator->createdBy->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED) {
                $delegateVerified = 'Yes';
            }
            $itemData = [
                'id' => $educator->getId(),
                'name' =>  [
                    'value' => $educator->name,
                    'editColumn' => true,
                ],
                'amount' => number_format($educator->amount, 0, '.', ','),
                'status' => \Solidarity\Educator\Entity\Educator::getHrStatus($educator->status),
                'school' => $educator->school->name,
                'city' => $educator->school->city->name,
                'delegateVerified' => $delegateVerified,
                'period' => $educator->period->getLabel(),
                'accountNumber' => $educator->accountNumber,
                'createdAt' => $educator->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $educator->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'name', 'label' => 'Ime'],
            ['name' => 'period', 'label' => 'Period', 'filterData' => $this->period->getFilterData()],
            ['name' => 'amount', 'label' => 'Iznos', 'rangeFilter' => ['type' => 'number']],
            ['name' => 'accountNumber', 'label' => 'Br računa'],
            ['name' => 'school', 'label' => 'Ime škole', 'filterData' => $this->school->getFilterData()],
            ['name' => 'city', 'label' => 'Grad'],
            ['name' => 'delegateVerified', 'label' => 'Delegat verifikovan'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Educator\Entity\Educator::getHrStatuses()],
            ['name' => 'createdAt', 'label' => 'Kreirano u'],
        ];

        return $columnDefinitions;
    }

}
