<?php

namespace Solidarity\Frontend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\ContentEditor\Contracts\BlockViewInterface;
use Skeletor\ContentEditor\Exceptions\TemplateNotFoundException;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Page\Repository\PageRepository;
use Solidarity\Page\Service\Page;

class PageAction extends BaseAction
{
    use LocalePreferenceTrait;

    public function __construct(
        Logger $logger,
        Config $config,
        Engine $template,
        Navigation $navigationService,
        SocialLinks $socialLinks,
        \Solidarity\Frontend\Service\Session $session,
        protected PageRepository $pageRepository,
        protected BlockViewInterface $blockView,
        protected Locale $locale
    ) {
        parent::__construct($logger, $config, $template, $navigationService, $socialLinks, $session);
        $this->setGlobalVariable('isHome', false);
        $this->setGlobalVariable(
            'homeSocialImgPath',
            $this->getConfig()->offsetGet('baseUrl') . FRONT_ASSET_URL . '/images/home.jpg'
        );
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ): \GuzzleHttp\Psr7\Response {
        try {
            $slug = $request->getAttribute('slug');
            if (!$slug) {
                throw new NotFoundException();
            }
            if ($redirect = $this->resolveLocalePreference($slug)) {
                return $redirect;
            }
            $page = $this->pageRepository->findPublishedBySlugAndLocale($slug, $this->locale->current());
            if (!$page) {
                throw new NotFoundException();
            }
            if($page->isLoginProtected && !$this->session->isLoggedIn()) {
                throw new NotFoundException();
            }

            if ($redirect = $this->resolveRedirectsBasedOnSession($slug)) {
                return $redirect;
            }

            $this->setSEO($page);
            $this->setGlobalVariable(
                'canonical',
                $this->getConfig()->offsetGet('baseUrl') . $this->locale->localize('/' . $page->slug)
            );
            $this->setLocalizedSwitcher($page);
            $content = $this->blockView->getView($page->blockData ?? []);
            $mainClassName = $page->slug === 'homepage' ? '' : 'content';
        } catch (TemplateNotFoundException $e) {
            // If the template for a block is missing, we still want to show the page
        }
        return $this->respond('page/page', [
            'webpSupport' => (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') >= 0),
            'content' => $content,
        ]);
    }

    function resolveRedirectsBasedOnSession($slug): ?\GuzzleHttp\Psr7\Response
    {
        // @TODO add english slugs, check what else is required
        if ($this->session->isLoggedIn()
            && in_array($slug, ['registracija-donatora', 'logovanje', 'potvrdi-email'], true)) {
            return $this->redirect($this->locale->localizeUrl('/instrukcije-za-uplatu'));
        }
        return null;
    }

    /**
     * Point the language switcher at this page's sibling slug in each locale,
     * falling back to the localized homepage where no translation exists yet.
     */
    private function setLocalizedSwitcher(\Solidarity\Page\Entity\Page $page): void
    {
        $slugs = $this->pageRepository->getLocalizedSlugs($page->translationGroupId);
        $slugs[$this->locale->current()] = $page->slug; // a page is always its own variant

        $alternates = [];
        foreach ($this->locale->available() as $loc) {
            $alternates[$loc] = isset($slugs[$loc])
                ? $this->locale->localize('/' . $slugs[$loc], $loc)
                : $this->locale->localize('/', $loc);
        }
        $this->setGlobalVariable('localeAlternates', $alternates);
    }
}
