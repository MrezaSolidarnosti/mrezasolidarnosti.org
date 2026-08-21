<?php
namespace Solidarity\Period\Service;

use Solidarity\Period\Repository\PeriodRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;

class Period extends TableView
{

    /**
     * @param PeriodRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        PeriodRepository $repo, Session $user, Logger $logger,
        \Skeletor\Core\Activity\Service\Activity $activity) {
        parent::__construct($repo, $user, $logger, activity: $activity);
    }

    public function getFilterData($params = [], $limit = null, $order = null, $property = 'name')
    {
        $periods = [];
        foreach ($this->repo->fetchAll(['active' => 1]) as $period) {
            $periods[$period->id] = $period->getLabel();
        }

        return $periods;
    }

    public function prepareEntities($entities)
    {
        $items = [];
        foreach ($entities as $period) {
            $itemData = [
                'id' => $period->getId(),
                'project' => $period->project->code,
                'month' => $period->month,
                'year' => $period->year,
                'type' => $period->type,
                'active' => $period->active,
                'processing' => $period->processing,
                'createdAt' => $period->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $period->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {

        $columnDefinitions = [
            ['name' => 'project', 'label' => 'project'],
            ['name' => 'month', 'label' => 'Month'],
            ['name' => 'year', 'label' => 'Year'],
            ['name' => 'type', 'label' => 'Type'],
            ['name' => 'active', 'label' => 'Active'],
            ['name' => 'processing', 'label' => 'Processing'],
            ['name' => 'createdAt', 'label' => 'Created at'],
        ];

        return $columnDefinitions;
    }

}
