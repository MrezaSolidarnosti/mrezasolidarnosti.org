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
}