<?php
namespace Solidarity\Transaction\Service;

use Solidarity\Transaction\Repository\ProjectRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Transaction\Filter\Transaction as TransactionFilter;

class Project extends TableView
{
    /**
     * @param ProjectRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        ProjectRepository $repo, Session $user, Logger $logger, TransactionFilter $filter,
        \Skeletor\Core\Activity\Service\Activity $activity) {
        parent::__construct($repo, $user, $logger, $filter, activity: $activity);
    }

    public function getFilterData($params = [], $limit = null, $order = null, $property = 'name')
    {
        $data = [];
        $params = $this->applyTenantFilter($params);
        foreach ($this->repo->fetchAll($params, $limit, $order) as $entity) {
            $data[$entity->id] = $entity->code .' - '. $entity->{$property};
        }

        return $data;
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Transaction\Entity\Round $round */
        foreach ($entities as $transaction) {
            $itemData = [
                'id' => $transaction->getId(),
                'name' => $transaction->name,
                'code' => $transaction->code,
                'createdAt' => $transaction->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $transaction->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {

        $columnDefinitions = [
            ['name' => 'name', 'label' => 'Name'],
            ['name' => 'code', 'label' => 'Code'],
//            ['name' => 'updatedAt', 'label' => 'Updated at', 'priority' => 8],
            ['name' => 'createdAt', 'label' => 'Created at', 'priority' => 9],
        ];

        return $columnDefinitions;
    }

}