<?php

namespace Solidarity\Backend\Action;

use Skeletor\Core\Config\Config;
use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Action\Web\Html;
use Skeletor\Translator\Service\Translator;

/**
 * Clears the Translator's Redis cache for every available language.
 *
 * The Translator caches the full translation set per language under
 * `<cachePrefix>translations#<langId>#` with a 24h TTL, and nothing invalidates
 * it automatically. So after editing the `translation` table directly (a manual
 * SQL import, a bulk fix, etc.) the site keeps serving the stale set for up to a
 * day. Run this to force a reload from the DB on the next request.
 *
 *   php public/cli.php resetTranslationsCache run
 *
 * @TODO Move this into the Skeletor framework (e.g. a Skeletor\Translator CLI
 *       action wired via a default cliMap entry) so every Skeletor project gets
 *       `resetTranslationsCache` for free instead of re-implementing it. The only
 *       app-specific part here is the namespace + cliMap registration.
 */
class ResetTranslationsCache extends Html
{
    public function __construct(
        Logger $logger, Config $config, Engine $template, private Translator $translator,
    ) {
        parent::__construct($logger, $config, $template);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $languages = $this->translator->getAvailableLanguages();

        if (empty($languages)) {
            echo 'No languages found — nothing to reset. (Is the `language` table seeded?)' . PHP_EOL;
            return $response;
        }

        $count = 0;
        foreach ($languages as $language) {
            $this->translator->setLanguage($language->code);
            $this->translator->resetCache();
            echo sprintf('  cleared translation cache: code=%s id=%d', $language->code, $language->getId()) . PHP_EOL;
            $count++;
        }

        echo sprintf('Done — reset %d language cache(s). The next request reloads from the DB.', $count) . PHP_EOL;

        return $response;
    }
}
