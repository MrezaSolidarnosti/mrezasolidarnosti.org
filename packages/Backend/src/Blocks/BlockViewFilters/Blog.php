<?php

namespace Solidarity\Backend\Blocks\BlockViewFilters;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Post\Service\Post;

class Blog implements BlockViewFilterInterface
{
    /** Posts per batch - the initial render and every load more request. */
    const int PER_PAGE = 9;

    const string LOAD_MORE_URL = '/blog/load-more';

    public function __construct(
        private Post $postService
    )
    {

    }

    public function filter(array $data): array
    {
        $data['posts'] = $this->postService->getPublicPosts(0, self::PER_PAGE);
        $data['hasMore'] = $this->postService->countPublicPosts() > count($data['posts']);
        $data['loadMoreUrl'] = self::LOAD_MORE_URL;
        return $data;
    }
}
