<?php
namespace Solidarity\Delegate\Service;

use Solidarity\Delegate\Repository\DelegateRepository;
use Skeletor\Core\TableView\Service\TableView;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\User\Service\Session;
use Solidarity\Beneficiary\Repository\BeneficiaryRepository;
use Solidarity\Delegate\Filter\Delegate as DelegateFilter;
use Solidarity\School\Service\School;
use Solidarity\School\Service\SchoolType;
use Solidarity\Transaction\Service\Project;

class Delegate extends TableView
{

    /**
     * @param DelegateRepository $repo
     * @param Session $user
     * @param Logger $logger
     */
    public function __construct(
        DelegateRepository $repo, Session $user, Logger $logger, DelegateFilter $filter, private \DateTime $dt,
        private SchoolType $schoolType, private Project $project,
        private BeneficiaryRepository $beneficiaryRepo, private School $school,
        \Skeletor\Core\Activity\Service\Activity $activity
    ) {
        parent::__construct($repo, $user, $logger, $filter, activity: $activity);
    }

    public function getAffectedDelegates()
    {
        return $this->repo->getAffectedDelegates();
    }

    public function create(array $data)
    {
        $schoolIds = array_filter(array_map('intval', $data['schools'] ?? []));

        $entity = parent::create($data);

        if (!empty($schoolIds)) {
            foreach ($schoolIds as $schoolId) {
                $this->beneficiaryRepo->assignOrphanedBeneficiariesToDelegate($schoolId, $entity->getId());
            }
        }

        return $entity;
    }

    public function update(array $data)
    {
        // The round-start mail is gone: no template posts `sendRoundStartMail`, no Mailer
        // method sends it, and the branch that used to stamp `formLinkSent = 1` was claiming
        // a mail had gone out that nothing sent. Removed with the rest of the payout round.
        $newSchoolIds = array_filter(array_map('intval', $data['schools'] ?? []));

        // Diff old vs new for beneficiary reassignment
        $oldDelegate = $this->repo->getById((int) $data['id']);
        $oldSchoolIds = [];
        foreach ($oldDelegate->schools as $school) {
            $oldSchoolIds[] = $school->getId();
        }
        $removedSchoolIds = array_diff($oldSchoolIds, $newSchoolIds);
        $addedSchoolIds = array_diff($newSchoolIds, $oldSchoolIds);

        // nullifyCreatedByForDelegate is scoped to the *delegate*, not to the school that
        // triggered it, so removing one school releases the kept schools' beneficiaries as
        // collateral. When that happens the reclaim has to cover everything still on the list or
        // a kept school is left permanently ownerless. With nothing released there is nothing to
        // restore, so an ordinary add reclaims only what it added — reclaiming the whole list
        // unconditionally would sweep up orphans an admin had deliberately unassigned.
        $reclaimSchoolIds = $addedSchoolIds;
        if (!empty($removedSchoolIds)) {
            $this->beneficiaryRepo->nullifyCreatedByForDelegate((int) $data['id']);
            $reclaimSchoolIds = $newSchoolIds;
        }

        $entity = parent::update($data);

        // Release first, then reclaim: reversed, the nullify would undo the assignment it is
        // supposed to precede.
        foreach ($reclaimSchoolIds as $schoolId) {
            $this->beneficiaryRepo->assignOrphanedBeneficiariesToDelegate($schoolId, (int) $data['id']);
        }

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
            $projects = [];
            foreach ($delegate->projects as $project) {
                $projects[] = $project->code;
            }
            $itemData = [
                'id' => $delegate->getId(),
                'email' =>  [
                    'value' => $delegate->email,
                    'editColumn' => true,
                ],
                'name' => $delegate->name .' ('. implode(', ', $projects) . ')',
                'p.id' => implode(', ', $projects),
                's.id' => implode(',<br /> ', array_map(fn($s) => $s->name, $delegate->schools->toArray())),
                // todo add city after school name
                'schoolType' => implode(', ', array_unique(array_filter(array_map(fn($s) => $s->type?->name, $delegate->schools->toArray())))),
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
            ['name' => 'p.id', 'label' => 'Projekat', 'filterData' => $this->project->getFilterData()],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Solidarity\Delegate\Entity\Delegate::getHrStatuses()],
            ['name' => 'schoolType', 'label' => 'Tip škole', 'filterData' => $this->schoolType->getFilterData()],
            ['name' => 's.id', 'label' => 'Škola', 'filterData' => $this->school->getFilterData()],
//            ['name' => 'city', 'label' => 'City'],
//            ['name' => 'updatedAt', 'label' => 'Updated at', 'priority' => 8],
            ['name' => 'createdAt', 'label' => 'Registrovan', 'priority' => 9],
        ];

        return $columnDefinitions;
    }

}
