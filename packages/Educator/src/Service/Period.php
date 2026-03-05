<?php
namespace Solidarity\Educator\Service;

use Solidarity\Delegate\Service\Delegate;
use Solidarity\Educator\Repository\PeriodRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Educator\Filter\Educator as EducatorFilter;
use Tamtamchik\SimpleFlash\Flash;

class Period extends TableView
{

    /**
     * @param PeriodRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        PeriodRepository $repo, Session $user, Logger $logger, EducatorFilter $filter, private \DateTime $dt,
        private Delegate $delegate
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getFilterData($params = [], $limit = null, $order = null, $property = 'name')
    {
        $periods = [];
        foreach ($this->repo->fetchAll(['active' => 1]) as $period) {
//            $periods[$period->id] = sprintf('%d-%d-%s', $period->month, $period->year, $period->type);
            $periods[$period->id] = $period->getLabel();
        }

        return $periods;
    }

    public function startNewRound()
    {
        return $this->repo->startNewRound();
    }

    public function getForMapping()
    {
        return $this->repo->fetchForMapping();
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Educator\Entity\Educator $educator */
        foreach ($entities as $educator) {
            // @TODO make sure all educators have delegate
            $delegate = $this->delegate->getEntities(['schoolName' => $educator->schoolName, 'city' => $educator->city]);
            $delegateVerified = 'No';
            if (count($delegate) && ($delegate[0]->status === \Solidarity\Delegate\Entity\Delegate::STATUS_VERIFIED)) {
                $delegateVerified = 'Yes';
            }
            $itemData = [
                'id' => $educator->getId(),
                'month' => $educator->month,
                'year' => $educator->year,
                'type' => $educator->type,
                'active' => $educator->active,
                'processing' => $educator->processing,
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
