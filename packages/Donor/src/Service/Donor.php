<?php
namespace Solidarity\Donor\Service;

use Solidarity\Donor\Repository\DonorRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Donor\Filter\Donor as DonorFilter;
use Solidarity\Mailer\Service\Mailer;
use Solidarity\Transaction\Service\Project;
use Tamtamchik\SimpleFlash\Flash;

class Donor extends TableView
{

    /**
     * @param DonorRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        DonorRepository $repo, Session $user, Logger $logger, DonorFilter $filter, private \DateTime $dt,
        private Mailer $mailer, private Project $project
    ) {
        parent::__construct($repo, $user, $logger, $filter);
    }

    public function getDonorsByProject($project)
    {
        return $this->repo->getDonorsByProject($project);
    }

    public function create(array $data)
    {
        $entity = $this->getEntities(['email' => $data['email']]);
        if (count($entity)) {
            $data['id'] = $entity[0]->id;
            $entity = parent::update($data);
        } else {
            $entity = parent::create($data);
        }
        //@TODO
//        $this->mailer->sendDonorRegisteredMail($entity->email);

        return $entity;
    }

    public function prepareEntities($entities)
    {
        $items = [];
        /* @var \Solidarity\Donor\Entity\Donor $donor */
        foreach ($entities as $donor) {
            $projects = [];
            foreach ($donor->projects as $project) {
                $projects[] = $project->code;
            }
            $itemData = [
                'id' => $donor->getId(),
                'email' =>  [
                    'value' => $donor->email .' ('. implode(', ', $projects) . ')',
                    'editColumn' => true,
                ],
//                'amount' => number_format($donor->amount, 0, '.', ','),
                'p.id' => implode(', ', $projects),
                'status' => \Solidarity\Donor\Entity\Donor::getHrStatus($donor->status),
                'isActive' => ($donor->isActive) ? 'Da': 'Ne',
                'createdAt' => $donor->getCreatedAt()->format('d.m.Y'),
                'updatedAt' => $donor->getUpdatedAt()->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $donor->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'p.id', 'label' => 'project', 'filterData' => $this->project->getFilterData()],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Donor\Entity\Donor::getHrStatuses()],
            ['name' => 'isActive', 'label' => 'Aktivan', 'filterData' => [0 => 'No', 1 => 'Yes']],
//            ['name' => 'amount', 'label' => 'Amount', 'rangeFilter' => ['type' => 'number']],
            ['name' => 'updatedAt', 'label' => 'Updated at'],
            ['name' => 'createdAt', 'label' => 'Created at'],
        ];

        return $columnDefinitions;
    }

}