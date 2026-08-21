<?php
namespace Solidarity\User\Service;

use Psr\Log\LoggerInterface as Logger;
use Skeletor\Address\Model\LocationInterface;
use Skeletor\Core\TableView\Service\TableView as TableView;
use Skeletor\Tenant\Model\Tenant;
use Skeletor\Tenant\Repository\TenantRepositoryInterface;
use Skeletor\User\Repository\UserRepositoryInterface as UserRepo;

class User extends \Skeletor\User\Service\User
{
    public function __construct(
        UserRepo $repository, \Skeletor\User\Service\Session $userSession,
        Logger $logger, \Solidarity\User\Filter\User $filter,
        \Skeletor\Core\Activity\Service\Activity $activity,
        protected ?TenantRepositoryInterface $tenant = null
    ) {
        // Required, not optional: PHP-DI skips optional parameters when autowiring, so a
        // nullable $activity would always arrive as null and nothing would ever be logged.
        parent::__construct($repository, $userSession, $logger, $filter, $activity, $tenant);
    }

    /**
     * @param array $data
     * @return mixed|LocationInterface|void
     */
    public function prepareEntities($entities)
    {
        $items = [];
        foreach ($entities as $user) {
            $itemData = [
                'id' => $user->getId(),
                'email' =>  [
                    'value' => $user->email,
                    'editColumn' => true,
                ],
                'isActive' => ($user->isActive) ? 'Yes':'No',
                'role' => $user::getHrRole($user->role),
                'displayName' => $user->displayName,
                'createdAt' => $user->createdAt->format('d.m.Y'),
                'updatedAt' => $user->updatedAt->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $user->getId(),
            ];
        }
        return $items;
    }

    public function compileTableColumns()
    {
        $columnDefinitions = [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'isActive', 'label' => 'Is active', 'filterData' => [1 => 'Active', '0' => 'Inactive']],
            ['name' => 'role', 'label' => 'Role', 'filterData' => \Solidarity\User\Entity\User::getHrRoles()],
            ['name' => 'displayName', 'label' => 'Display Name'],
            ['name' => 'updatedAt', 'label' => 'Updated at', 'rangeFilter' => ['type' => 'date']],
            ['name' => 'createdAt', 'label' => 'Created at', 'rangeFilter' => ['type' => 'date']],
        ];

        return $columnDefinitions;
    }


    public function getEntityData($id)
    {
        $entity = $this->repo->getById($id);

        return [
            'id' => $entity->id,
            'email' => $entity->email,
            'isActive' => $entity->isActive,
            'role' => $entity->role,
            'displayName' => $entity->displayName,
        ];
    }
}