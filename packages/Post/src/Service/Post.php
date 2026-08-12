<?php

namespace Solidarity\Post\Service;

use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\TableView\Service\TableView;
use Skeletor\Tenant\Repository\TenantRepositoryInterface as TenantRepository;
use Skeletor\User\Service\Session;
use Solidarity\Post\Repository\PostRepository;

class Post extends TableView
{
    public function __construct(
        PostRepository $repository,
        Session $session,
        Logger $logger,
        \Solidarity\Post\Filter\Post $filter,
        ?TenantRepository $tenant = null,
    ) {
        parent::__construct($repository, $session, $logger, $filter, $tenant);
    }

    public function prepareEntities($entities): array
    {
        $items = [];
        foreach($entities as $entity) {
            $itemData = [
                'id' => $entity->id,
                'title' =>  [
                    'value' => $entity->title,
                    'editColumn' => true,
                ],
                'slug' => $entity->slug,
                'status' => \Solidarity\Post\Entity\Post::getStatusHR($entity->status),
                'publishAt' => $entity->publishAt?->format('d.m.Y'),
                'createdAt' => $entity->createdAt->format('d.m.Y'),
                'updatedAt' => $entity->updatedAt->format('d.m.Y'),
            ];
            $items[] = [
                'columns' => $itemData,
                'id' => $entity->id,
            ];
        }
        return $items;
    }

    public function compileTableColumns(): array
    {
        return [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'title', 'label' => 'Name'],
            ['name' => 'slug', 'label' => 'Slug'],
            ['name' => 'status', 'label' => 'Status', 'filterData' => \Skeletor\Blog\Entity\Post::getStatuses()],
            ['name' => 'publishAt', 'label' => 'Publish at', 'rangeFilter' => ['type' => 'date']],
            ['name' => 'createdAt', 'label' => 'Created', 'rangeFilter' => ['type' => 'date']],
            ['name' => 'updatedAt', 'label' => 'Updated', 'rangeFilter' => ['type' => 'date']]
        ];
    }

    public function getPublicPosts(int $offset = 0, ?int $limit = null): array
    {
        return $this->repo->getPublicPosts($offset, $limit);
    }

    public function countPublicPosts(): int
    {
        return $this->repo->countPublicPosts();
    }
}