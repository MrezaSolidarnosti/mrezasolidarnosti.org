<?php

namespace Solidarity\Post\Repository;

use Doctrine\ORM\QueryBuilder;
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

    /**
     * Posts visible on the public site: everything published, plus scheduled
     * posts whose publishAt moment has already arrived. Newest first.
     *
     * @return Post[]
     */
    public function getPublicPosts(int $offset = 0, ?int $limit = null): array
    {
        return $this->publicPostsQuery('p')
            ->orderBy('p.publishAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** How many posts getPublicPosts() can return in total - drives the load more button. */
    public function countPublicPosts(): int
    {
        return (int) $this->publicPostsQuery('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    /** The public visibility criteria, shared by the listing and its count so they can't drift. */
    private function publicPostsQuery(string $select): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($select)
            ->from(static::ENTITY, 'p')
            ->where('p.status = :published OR (p.status = :scheduled AND p.publishAt IS NOT NULL AND p.publishAt <= :now)')
            ->setParameter('published', Post::STATUS_PUBLISHED)
            ->setParameter('scheduled', Post::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTime());
    }
}