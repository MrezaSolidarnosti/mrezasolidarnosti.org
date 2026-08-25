<?php
namespace Solidarity\Frontend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Skeletor\ContentEditor\Contracts\BlockViewInterface;
use Skeletor\ContentEditor\Exceptions\TemplateNotFoundException;
use Skeletor\Core\Mapper\NotFoundException;
use Skeletor\ThemeSettings\Navigation\Service\Navigation;
use Skeletor\ThemeSettings\SocialLinks\Service\SocialLinks;
use Solidarity\Frontend\Service\Locale;
use Solidarity\Page\Repository\PageRepository;
use Psr\Log\LoggerInterface as Logger;

class Index extends BaseAction
{
    use LocalePreferenceTrait;

    public function __construct(
        Logger $logger, Config $config, Engine $template, private \Solidarity\Donor\Service\Donor $donor,
        protected Navigation $navigationService,
        protected SocialLinks $socialLinks,
        protected PageRepository $pageRepository,
        protected BlockViewInterface $blockView,
        \Solidarity\Frontend\Service\Session $session,
        protected Locale $locale,
    ) {
        parent::__construct($logger, $config, $template, $this->navigationService, $this->socialLinks, $session);
    }

    public function __invoke(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseInterface $response
    ) {
        if ($redirect = $this->resolveLocalePreference(null)) {
            return $redirect;
        }
        $this->setGlobalVariable('isHome', true);
        $this->setGlobalVariable('title', 'Mreža Solidarnosti');
        try {
            $current = $this->locale->current();
            $homepage = $this->pageRepository->findPublishedHomeByLocale($current);
            if (!$homepage && $current !== $this->locale->default()) {
                // Fall back to the default-locale homepage so home never 404s
                // while its translation is still being prepared.
                $homepage = $this->pageRepository->findPublishedHomeByLocale($this->locale->default());
            }
            if (!$homepage) {
                throw new NotFoundException();
            }
            $this->setSEO($homepage);
            $this->setGlobalVariable('canonical', $this->getConfig()->offsetGet('baseUrl') . $this->locale->localize('/'));
            $this->setHomeSwitcher();
            $content = $this->blockView->getView($homepage->blockData ?? []);
        } catch(TemplateNotFoundException $e) {
            throw new NotFoundException();
        }

        return $this->respond('index/index', [
            'webpSupport' => (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') >= 0),
            'content' => $content,
        ]);
    }

    /** Language switcher on the homepage points at each locale's root. */
    private function setHomeSwitcher(): void
    {
        $alternates = [];
        foreach ($this->locale->available() as $loc) {
            $alternates[$loc] = $this->locale->localize('/', $loc);
        }
        $this->setGlobalVariable('localeAlternates', $alternates);
    }
}