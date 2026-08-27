<?php
namespace Solidarity\Period\Service;

use Solidarity\Period\Entity\Period as PeriodEntity;
use Solidarity\Period\Repository\PeriodRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Period\Filter\Period as PeriodFilter;
use Solidarity\Transaction\Service\Project as ProjectService;

class Period extends TableView
{

    /**
     * @param PeriodRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    /**
     * The filter is passed on purpose, and was not before.
     *
     * CrudService::create()/update() guard on `if ($this->filter)`, so leaving that slot empty
     * meant raw POST data went straight to the factory: Period\Filter existed but never ran.
     * AbstractFactory then assigned each value verbatim, which PHP's non-strict coercion hid
     * for '2026' -> int and '1' -> bool, but not for an empty "Max iznos" — '' is not a numeric
     * string, so `'' -> int $maxAmount` was a TypeError and a 500 on save.
     */
    public function __construct(
        PeriodRepository $repo, Session $user, Logger $logger, PeriodFilter $filter,
        private ProjectService $project,
        \Skeletor\Core\Activity\Service\Activity $activity) {
        parent::__construct($repo, $user, $logger, $filter, activity: $activity);
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
                'month' => PeriodEntity::getHrMonth($period->month),
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
            // Filterable: the two projects run separate rounds and the unfiltered list mixes
            // them together. 'project' carries no dot, so TableViewRepository prefixes it to
            // a.project and compares the ManyToOne against the chosen id.
            ['name' => 'project', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
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
