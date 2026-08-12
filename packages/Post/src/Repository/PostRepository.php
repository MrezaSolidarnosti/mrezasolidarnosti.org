<?php

namespace Solidarity\Post\Repository;

use Skeletor\Core\TableView\Repository\TableViewRepository;
use Solidarity\Post\Entity\Post;
use Solidarity\Post\Factory\PostFactory;

class PostRepository extends TableViewRepository
{
    const string ENTITY = Post::class;
    const string FACTORY = PostFactory::class;

    public function getSearchableColumns(): array
    {
        return ['title', 'shortDescription'];
    }

    public function getPublicPosts(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(static::ENTITY, 'p')
            ->where('p.status = :published OR (p.status = :scheduled AND p.publishAt IS NOT NULL AND p.publishAt <= :now)')
            ->setParameter('published', Post::STATUS_PUBLISHED)
            ->setParameter('scheduled', Post::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.publishAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}