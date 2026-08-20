<?php

namespace Solidarity\Frontend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\ContentEditor\Contracts\BlockViewInterface;
use Solidarity\ContentEditor\Exceptions\TemplateNotFoundException;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Post\Service\Post;

/**
 * A single blog post at /blog/{slug}.
 *
 * Only publicly visible posts are served (see PostRepository::findPublicBySlug), and
 * the body is rendered from the post's content editor blocks through the ContentEditor
 * View - the core/* blocks, as opposed to the page builder blocks PageAction uses.
 */
class PostAction extends BaseAction
{
    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        Navigation $navigationService,
        SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        protected Post $postService,
        protected BlockViewInterface $blockView,
        protected Locale $locale
    ) {
        parent::__construct($logger, $config, $template, $navigationService, $socialLinks, $session);
        $this->setGlobalVariable('isHome', false);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ): \GuzzleHttp\Psr7\Response {
        $slug = $request->getAttribute('slug');
        if (!$slug) {
            throw new NotFoundException();
        }
        $post = $this->postService->getPublicPostBySlug($slug);
        if (!$post) {
            throw new NotFoundException();
        }

        $this->setSEO($post);
        $this->setGlobalVariable(
            'canonical',
            $this->getConfig()->offsetGet('baseUrl') . $this->locale->localize('/blog/' . $post->slug)
        );

        $content = '';
        try {
            $content = $this->blockView->getView($post->blockData ?? []);
        } catch (TemplateNotFoundException $e) {
            // If the template for a block is missing, we still want to show the post
        }

        return $this->respond('post/post', [
            'webpSupport' => (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') >= 0),
            'post' => $post,
            'content' => $content,
        ]);
    }
}
