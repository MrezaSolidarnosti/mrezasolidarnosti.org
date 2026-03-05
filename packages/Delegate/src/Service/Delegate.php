<?php
namespace Solidarity\Delegate\Service;

use Solidarity\Delegate\Repository\DelegateRepository;
use Solidarity\Delegate\Entity\Delegate as DelegateEntity;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Delegate\Filter\Delegate as DelegateFilter;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\School\Repository\SchoolTypeRepository;
use Solidarity\School\Service\SchoolType;

class Delegate extends TableView
{

    /**
     * @param DelegateRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        DelegateRepository $repo, Session $user, Logger $logger, DelegateFilter $filter, private \DateTime $dt,
        private Mailer $mailer, private SchoolType $schoolType
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getAffectedDelegates()
    {
        return $this->repo->getAffectedDelegates();
    }

    public function create(array $data)
    {
        $entity = parent::create($data);
        if ($entity->status === DelegateEntity::STATUS_VERIFIED) {
//            $this->mailer->sendRoundStartMailToDelegate($entity->email);
            //@todo add checkbox for sendRoundStartMail
//            $data['id'] = $entity->id;
//            $data['formLinkSent'] = 1;
//            $entity = parent::update($data);
        }

        return $entity;
    }

    public function update(array $data)
    {
        $sendMail = $data['sendRoundStartMail'] ?? 0;
        unset($data['sendRoundStartMail']);
        if ($sendMail) {
            $data['formLinkSent'] = 1;
        }
        $entity = parent::update($data);
//        if ($sendMail) {
//            $this->mailer->sendRoundStartMailToDelegate($entity->email);
//        }

        return $entity;
    }

    public function fetchTableData(
        $search, $filter, $offset, $limit, $order, $uncountableFilter = null, $idsToInclude = [], $idsToExclude = []
    ) {
        // delegate can only see own account
        if ($this->getUserSession()->getLoggedInEntityType() === 'delegate') {
            $uncountableFilter['id'] = $this->getUserSession()->getLoggedInUserId();
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
        /* @var \Solidarity\Delegate\Entity\Delegate $delegate */
        foreach ($entities as $delegate) {
            $itemData = [
                'id' => $delegate->getId(),
                'email' =>  [
                    'value' => $delegate->email,
                    'editColumn' => true,
                ],
                'name' => $delegate->name,
                'school' => $delegate->school->name,
                'schoolType' => $delegate->school->type->name,
                'phone' => $delegate->phone,
                'status' => \Solidarity\Delegate\Entity\Delegate::getHrStatus($delegate->status),
//                'updatedAt' => $delegate->getUpdatedAt()->format('d.m.Y'),
                'createdAt' => $delegate->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $delegate->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'name', 'label' => 'Ime'],
            ['name' => 'phone', 'label' => 'Telefon'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Delegate\Entity\Delegate::getHrStatuses()],
            ['name' => 'schoolType', 'label' => 'Tip škole', 'filterData' => $this->schoolType->getFilterData()],
            ['name' => 'school', 'label' => 'Škola'],
//            ['name' => 'city', 'label' => 'City'],
//            ['name' => 'updatedAt', 'label' => 'Updated at', 'priority' => 8],
            ['name' => 'createdAt', 'label' => 'Kreirano u', 'priority' => 9],
        ];

        return $columnDefinitions;
    }

}
