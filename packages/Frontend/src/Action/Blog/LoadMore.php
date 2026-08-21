<?php

namespace Solidarity\Frontend\Action\Blog;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Backend\Blocks\Blog\BlogViewFilter;
use Solidarity\Frontend\Action\BaseAction;
use Solidarity\Post\Service\Post;

/**
 * Next batch of public posts for the blog block's load more button.
 *
 * Returns rendered cards rather than raw post data so the markup stays in the
 * partialsGlobal::postCard partial the initial page render uses.
 */
class LoadMore extends BaseAction
{
    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        private Post $postService
    ) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        $offset = max(0, (int) ($request->getQueryParams()['offset'] ?? 0));

        try {
            $posts = $this->postService->getPublicPosts($offset, BlogViewFilter::PER_PAGE);
            $html = '';
            foreach ($posts as $post) {
                $html .= $this->template->render('partialsGlobal::postCard', ['post' => $post]);
            }
            $nextOffset = $offset + count($posts);

            return $this->returnWithData(true, [
                'html' => $html,
                'nextOffset' => $nextOffset,
                'hasMore' => $nextOffset < $this->postService->countPublicPosts(),
            ]);
        } catch (\Exception $e) {
            return $this->returnWithData(false, ['errors' => ['An unexpected error occurred, please try again.']], 400);
        }
    }
}
