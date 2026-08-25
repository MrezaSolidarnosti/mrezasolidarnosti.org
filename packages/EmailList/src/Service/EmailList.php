<?php
namespace Solidarity\EmailList\Service;

use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\EmailList\Repository\EmailListRepository;

class EmailList extends TableView
{
    public function __construct(
        EmailListRepository $repo, Session $user, Logger $logger, \Solidarity\EmailList\Filter\EmailList $filter,
        \Skeletor\Core\Activity\Service\Activity $activity) {
        parent::__construct($repo, $user, $logger, $filter, activity: $activity);
    }

    public function subscribe(string $email): void
    {
        $repo = $this->repo;
        $existing = $repo->findByEmail($email);
        if ($existing) {
            if (!$existing->isActive) {
                $this->update(['id' => $existing->getId(), 'email' => $email, 'isActive' => true]);
            }
            return;
        }

        $this->create(['email' => $email, 'isActive' => true]);
    }

    public function getFilterErrors()
    {
        return $this->filter->getErrors();
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\EmailList\Entity\EmailList $emailList */
        foreach ($entities as $emailList) {
            $itemData = [
                'id' => $emailList->getId(),
                'email' => $emailList->email,
                'isActive' => $emailList->isActive ? 'Da' : 'Ne',
                'createdAt' => $emailList->getCreatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $emailList->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'isActive', 'label' => 'Active', 'filterData' => [0 => 'Ne', 1 => 'Da']],
            ['name' => 'createdAt', 'label' => 'Prijavljen'],
        ];

        return $columnDefinitions;
    }
}
