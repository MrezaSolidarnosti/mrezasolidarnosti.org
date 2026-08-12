<?php

namespace Solidarity\Backend\Blocks\Blog;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Solidarity\Post\Service\Post;

class BlogViewFilter implements BlockViewFilterInterface
{
    public function __construct(
        private Post $postService
    )
    {

    }

    public function filter(array $data): array
    {
        $data['posts'] = $this->postService->getPublicPosts();
        return $data;
    }
}
