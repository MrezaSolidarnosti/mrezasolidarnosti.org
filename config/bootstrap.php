<?php
use Skeletor\Core\Security\Csrf;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Session\SessionManager;
use Laminas\Session\ManagerInterface;
use Laminas\Session\Config\SessionConfig;
use Monolog\ErrorHandler;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface as Logger;
use Skeletor\Core\Config\Config;
use Skeletor\Core\Mailer\Service\MailerInterface;
use Skeletor\Core\Security\Authorization\AuthorizationService;
use Skeletor\Core\Security\EntityRegistry;
use Skeletor\Image\Service\Image;
use Solidarity\Backend\Blocks\About\About;
use Solidarity\Backend\Blocks\Banner\Banner;
use Solidarity\Backend\Blocks\Blog\Blog;
use Solidarity\Backend\Blocks\Connect\Connect;
use Solidarity\Backend\Blocks\Contactcards\Contactcards;
use Solidarity\Backend\Blocks\Ctabanner\Ctabanner;
use Solidarity\Backend\Blocks\Direction\Direction;
use Solidarity\Backend\Blocks\Donate\Donate;
use Solidarity\Backend\Blocks\Faq\Faq;
use Solidarity\Backend\Blocks\Herotext\Herotext;
use Solidarity\Backend\Blocks\Instructionsintro\Instructionsintro;
use Solidarity\Backend\Blocks\Instructionstable\Instructionstable;
use Solidarity\Backend\Blocks\Projectsdisplay\Projectsdisplay;
use Solidarity\Backend\Blocks\Sidebyside\Sidebyside;
use Solidarity\Backend\Blocks\Threepillars\Threepillars;
use Solidarity\Backend\Blocks\Valuecards\Valuecards;
use Solidarity\Backend\Blocks\Whotocall\Whotocall;
use Solidarity\Backend\Blocks\Howitworks\Howitworks;
use Solidarity\Backend\Blocks\Howitworkstimeline\Howitworkstimeline;
use Solidarity\Backend\Blocks\Login\Login;
use Solidarity\Backend\Blocks\Loginsuccess\Loginsuccess;
use Solidarity\Backend\Blocks\Profiledata\Profiledata;
use Solidarity\Backend\Blocks\Registerconfirmemail\Registerconfirmemail;
use Solidarity\Backend\Blocks\Registerform\Registerform;
use Solidarity\Backend\Blocks\Registersuccessbox\Registersuccessbox;
use Solidarity\Backend\Blocks\Testimonials\Testimonials;
use Solidarity\Backend\Blocks\Whywearedifferent\Whywearedifferent;
use Solidarity\Backend\Blocks\Find\Find;
use Solidarity\Backend\Blocks\HeroStats\HeroStats;
use Solidarity\ContentEditor\BlockFilterFactory;
use Solidarity\ContentEditor\BlockFilters\Accordion;
use Solidarity\ContentEditor\BlockFilters\Chart;
use Solidarity\ContentEditor\BlockFilters\Columns;
use Solidarity\ContentEditor\BlockFilters\Divider;
use Solidarity\ContentEditor\BlockFilters\Embed;
use Solidarity\ContentEditor\BlockFilters\Footnotes;
use Solidarity\ContentEditor\BlockFilters\Gallery;
use Solidarity\ContentEditor\BlockFilters\Heading;
use Solidarity\ContentEditor\BlockFilters\HeadingFive;
use Solidarity\ContentEditor\BlockFilters\HeadingFour;
use Solidarity\ContentEditor\BlockFilters\HeadingSix;
use Solidarity\ContentEditor\BlockFilters\HeadingThree;
use Solidarity\ContentEditor\BlockFilters\HeadingTwo;
use Solidarity\ContentEditor\BlockFilters\Html;
use Solidarity\ContentEditor\BlockFilters\Image as ImageBlockFilter;
use Solidarity\ContentEditor\BlockFilters\OrderedList;
use Solidarity\ContentEditor\BlockFilters\Paragraph;
use Solidarity\ContentEditor\BlockFilters\Quote;
use Solidarity\ContentEditor\BlockFilters\Spacer;
use Solidarity\ContentEditor\BlockFilters\Table;
use Solidarity\ContentEditor\BlockFilters\Tabs;
use Solidarity\ContentEditor\BlockFilters\Timeline;
use Solidarity\ContentEditor\BlockFilters\UnorderedList;
use Solidarity\ContentEditor\Contracts\BlockFilterFactoryInterface;
use Solidarity\ContentEditor\Contracts\ContentEditorFilterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Tamtamchik\SimpleFlash\Flash;
use Skeletor\Core\Acl\Acl;
use \League\Flysystem\Filesystem;
use League\Plates\Engine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$containerBuilder = new \DI\ContainerBuilder;
/* @var \DI\Container $container */
$container = $containerBuilder
//    ->addDefinitions(require_once __DIR__ . '/config_web.php')
    ->build();

$container->set(ManagerInterface::class, function() use ($container) {
    // Get config values
    $config = $container->get(Config::class);
    $redisHost = array_keys($config->redis->hosts->toArray())[0];
    $redisPort = array_values($config->redis->hosts->toArray())[0];
    // ASCII only, and that is not cosmetic. appName is "Mreža Solidarnosti", so this used to
    // produce the cookie name "Mreža_Solidarnostifrontend" — and RFC 6265 requires a cookie
    // name to be a US-ASCII token. Chrome and Firefox accept the ž and carry on; **Safari
    // rejects the cookie outright**, so every request started a fresh session, login() wrote
    // into a session that was never sent back, and the donor arrived at a login-protected
    // page anonymous. It presented as "magic link works on desktop, 404s on iPhone", with
    // one orphaned logged-in session accumulating in Redis per attempt.
    //
    // Derived from appName rather than hardcoded so frontend and backend keep separate
    // cookies; the filter just guarantees the result is a legal cookie name.
    $sessionName = preg_replace(
        '/[^A-Za-z0-9_]/',
        '',
        str_replace(' ', '_', $config->appName . \Solidarity\Core\Environment::application())
    );

    // Set session name via ini_set BEFORE creating SessionConfig
    ini_set('session.name', $sessionName);
    ini_set('session.gc_maxlifetime', (string)(60*60*24));
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', sprintf('tcp://%s:%s?weight=1&timeout=1', $redisHost, $redisPort));

    // Which host this app answers on, taken from config rather than sniffed from $_SERVER.
    // Behind Cloudflare $_SERVER['HTTPS'] is not reliable, and X-Forwarded-Proto is attacker-
    // controllable on any request that does not actually come through the proxy. adminUrl is
    // absent from some configs, hence the fallback.
    $appUrl = (string) (\Solidarity\Core\Environment::isBackend()
        ? ($config->adminUrl ?? $config->baseUrl)
        : $config->baseUrl);

    $sessionConfig = new SessionConfig();
    $sessionConfig->setOptions([
        'remember_me_seconds' => 2592000, //2592000, // 30 * 24 * 60 * 60 = 30 days
        'use_cookies'         => true,
        'cookie_lifetime'     => 30 * 24 * 60 * 60,
        // Laminas only mirrors whatever session.* ini already holds, and nothing set these, so
        // the session cookie went out bare on every environment: readable by any script on the
        // page — which turns any XSS into a session takeover — and attached to cross-site
        // requests, widening the CSRF surface the form tokens exist to close.
        'cookie_httponly'     => true,
        // Lax, not Strict: the donor magic link is followed from an email client, and a Strict
        // cookie is withheld on that first cross-site navigation, so the donor would land on
        // the verify page with no session and be bounced.
        'cookie_samesite'     => 'Lax',
        // Derived, never hardcoded true: local dev runs on plain http (config-local baseUrl),
        // and a Secure cookie there is never sent back — every request would start a fresh
        // session and login would silently fail, exactly the way the non-ASCII cookie name
        // above broke Safari.
        'cookie_secure'       => str_starts_with($appUrl, 'https://'),
    ]);
    $session = new SessionManager($sessionConfig);
    $session->start();

    return $session;
});

/**
 * The concrete class resolves to the same instance as the interface.
 *
 * Only ManagerInterface was bound above — the one that sets the Redis save handler, names the
 * session and calls start(). Services that type-hint the concrete SessionManager (the frontend
 * donor Session, and every backend controller taking `SessionManager as Session`) were being
 * handed a **freshly autowired** manager instead: default config, no Redis handler, never
 * started.
 *
 * It appeared to work because Laminas' SessionArrayStorage proxies $_SESSION, so the second
 * instance reads whatever the started one populated — but only when the started one is
 * resolved first. That made login state depend on container resolution order, which is not a
 * thing that should decide whether somebody is logged in: adding one GET to the magic-link
 * flow reordered it, and donors authenticated successfully and then arrived anonymous.
 */
$container->set(\Laminas\Session\SessionManager::class, function() use ($container) {
    return $container->get(ManagerInterface::class);
});

$container->set(BlockFilterFactoryInterface::class, function() use ($container) {
    $blockFilterFactory = new BlockFilterFactory(
        $container->get(Image::class)
    );
    $blockFilterFactory->registerBlockFilter('core/paragraph', new Paragraph());
    $blockFilterFactory->registerBlockFilter('core/heading', new Heading());
    $blockFilterFactory->registerBlockFilter('core/headingtwo', new HeadingTwo());
    $blockFilterFactory->registerBlockFilter('core/headingthree', new HeadingThree());
    $blockFilterFactory->registerBlockFilter('core/headingfour', new HeadingFour());
    $blockFilterFactory->registerBlockFilter('core/headingfive', new HeadingFive());
    $blockFilterFactory->registerBlockFilter('core/headingsix', new HeadingSix());
    $blockFilterFactory->registerBlockFilter('core/unorderedList', new UnorderedList());
    $blockFilterFactory->registerBlockFilter('core/orderedList', new OrderedList());
    $blockFilterFactory->registerBlockFilter('core/quote', new Quote());
    $blockFilterFactory->registerBlockFilter('core/html', new Html());
    $blockFilterFactory->registerBlockFilter('core/image', new ImageBlockFilter());
    $blockFilterFactory->registerBlockFilter('core/gallery', new Gallery());
    $blockFilterFactory->registerBlockFilter('core/divider', new Divider());
    $blockFilterFactory->registerBlockFilter('core/embed', new Embed());
    $blockFilterFactory->registerBlockFilter('core/spacer', new Spacer());
    $blockFilterFactory->registerBlockFilter('core/columns', new Columns());
    $blockFilterFactory->registerBlockFilter('core/table', new Table());
    $blockFilterFactory->registerBlockFilter('core/chart', new Chart());
    $blockFilterFactory->registerBlockFilter('core/footnotes', new Footnotes());
    $blockFilterFactory->registerBlockFilter('core/accordion', new Accordion());
    $blockFilterFactory->registerBlockFilter('core/tabs', new Tabs());
    $blockFilterFactory->registerBlockFilter('core/timeline', new Timeline());
    return $blockFilterFactory;
});

$container->set(ContentEditorFilterInterface::class, function() use ($container) {
    return $container->get(\Solidarity\ContentEditor\Filter::class);
});

// Renders core/* editor blocks (posts) from themes/frontend/contentEditor,
// e.g. the core/paragraph block from contentEditor/core/paragraph.php.
$container->set(\Solidarity\ContentEditor\Contracts\BlockViewInterface::class, function() use ($container) {
    $view = new \Solidarity\ContentEditor\View(
        $container->get(Engine::class),
        APP_PATH . '/themes/frontend/contentEditor'
    );

    $view->registerViewFilter('core/image', new \Solidarity\ContentEditor\BlockViewFilters\Image(
        $container->get(Image::class)
    ));

    $view->registerViewFilter('core/gallery', new \Solidarity\ContentEditor\BlockViewFilters\Gallery(
        $container->get(Image::class)
    ));

    $view->registerViewFilter('core/embed', new \Solidarity\ContentEditor\BlockViewFilters\Embed());

    return $view;
});
// Content Editor

$container->set(\Skeletor\ContentEditor\Contracts\BlockParserFactoryInterface::class, function() use ($container) {
    $blockParserFactory =  new \Skeletor\ContentEditor\Factory\BlockParserFactory(
        $container->get(Image::class)
    );

    $blockParserFactory->registerBlockParser(HeroStats::NAME, new HeroStats());
    $blockParserFactory->registerBlockParser(Find::NAME, new Find(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Direction::NAME, new Direction());
    $blockParserFactory->registerBlockParser(Connect::NAME, new Connect());
    $blockParserFactory->registerBlockParser(Whywearedifferent::NAME, new Whywearedifferent());
    $blockParserFactory->registerBlockParser(Howitworks::NAME, new Howitworks(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Testimonials::NAME, new Testimonials());
    $blockParserFactory->registerBlockParser(Faq::NAME, new Faq());
    $blockParserFactory->registerBlockParser(Herotext::NAME, new Herotext());
    $blockParserFactory->registerBlockParser(Contactcards::NAME, new Contactcards(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Sidebyside::NAME, new Sidebyside());
    $blockParserFactory->registerBlockParser(Projectsdisplay::NAME, new Projectsdisplay(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Threepillars::NAME, new Threepillars(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Banner::NAME, new Banner());
    $blockParserFactory->registerBlockParser(Whotocall::NAME, new Whotocall());
    $blockParserFactory->registerBlockParser(Ctabanner::NAME, new Ctabanner());
    $blockParserFactory->registerBlockParser(About::NAME, new About());
    $blockParserFactory->registerBlockParser(Valuecards::NAME, new Valuecards(
        $container->get(Image::class)
    ));
    $blockParserFactory->registerBlockParser(Howitworkstimeline::NAME, new Howitworkstimeline());
    $blockParserFactory->registerBlockParser(Registerform::NAME, new Registerform());
    $blockParserFactory->registerBlockParser(Registerconfirmemail::NAME, new Registerconfirmemail());
    $blockParserFactory->registerBlockParser(Registersuccessbox::NAME, new Registersuccessbox());
    $blockParserFactory->registerBlockParser(Login::NAME, new Login());
    $blockParserFactory->registerBlockParser(Loginsuccess::NAME, new Loginsuccess());
    $blockParserFactory->registerBlockParser(Profiledata::NAME, new Profiledata());
    $blockParserFactory->registerBlockParser(Donate::NAME, new Donate());
    $blockParserFactory->registerBlockParser(Instructionsintro::NAME, new Instructionsintro());
    $blockParserFactory->registerBlockParser(Instructionstable::NAME, new Instructionstable());
    $blockParserFactory->registerBlockParser(Blog::NAME, new Blog());

    return $blockParserFactory;
});

$container->set(\Skeletor\ContentEditor\Contracts\ContentEditorParserInterface::class, function() use ($container) {
    $parser = new \Skeletor\ContentEditor\Parser(
        $container->get(\Skeletor\ContentEditor\Contracts\BlockParserFactoryInterface::class)
    );
//    $parser->registerCustomData('customName', 'blockName or empty for all blocks');
    return $parser;
});

$container->set(\Skeletor\ContentEditor\Contracts\BlockViewInterface::class, function() use ($container) {
    $view = new \Skeletor\ContentEditor\View(
        $container->get(Engine::class),
        APP_PATH . '/themes/frontend/blocks'
    );

    $view->registerViewFilter(HeroStats::NAME, new \Solidarity\Backend\Blocks\HeroStats\HeroStatsViewFilter(
        $container->get(\Solidarity\Donor\Service\Donor::class),
        $container->get(\Solidarity\Beneficiary\Service\Beneficiary::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class),
        // For the historical adjustment — the confirmed money the legacy app destroyed when
        // it cascade-deleted inactive donors. See config `historicalAdjustment`.
        $container->get(Config::class)
    ));

    $view->registerViewFilter(Blog::NAME, new \Solidarity\Backend\Blocks\Blog\BlogViewFilter(
        $container->get(\Solidarity\Post\Service\Post::class)
    ));

    $view->registerViewFilter(Profiledata::NAME, new \Solidarity\Backend\Blocks\Profiledata\ProfiledataViewFilter(
        $container->get(\Solidarity\Frontend\Service\Session::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    $view->registerViewFilter(Donate::NAME, new \Solidarity\Backend\Blocks\Donate\DonateViewFilter(
        $container->get(\Solidarity\Frontend\Service\Session::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    $view->registerViewFilter(Instructionsintro::NAME, new \Solidarity\Backend\Blocks\Instructionsintro\InstructionsintroViewFilter(
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    // Wrapped last, so every filter above is registered through the decorator onto the same
    // view. Block templates echo their link fields raw, so links authored as "doniraj" are
    // normalised to "/doniraj" and swapped for the localised slug off the default locale.
    return new \Solidarity\Frontend\Service\LocalizingBlockView(
        $view,
        $container->get(\Solidarity\Frontend\Service\Locale::class)
    );
});

$container->set(\Skeletor\Exporter\Contracts\ExporterFactoryInterface::class, function() use ($container) {
    return new \Skeletor\Exporter\ExporterFactory($container->get(\Skeletor\Translator\Service\Translator::class));
});

$container->set(\Skeletor\User\Repository\UserRepositoryInterface::class, function() use ($container) {
    return $container->get(\Solidarity\User\Repository\UserRepository::class);
});

$container->set(Engine::class, function() use ($container) {
    $path = 'admin';
    if (\Solidarity\Core\Environment::isBackend()) {
        $path = 'admin';
    }
    if (\Solidarity\Core\Environment::isFrontend()) {
        $path = 'frontend';
    }
    $defaultTheme = APP_PATH . '/vendor/dj_avolak/skeletor/themes/' . $path;
    $mailTheme = APP_PATH . '/themes/email';
    $theme = APP_PATH . '/themes/' . $path;
    $plates = new \League\Plates\Engine($theme);
    $plates->addFolder('defaultTheme', $defaultTheme, true);
    $plates->addFolder('emailTheme', $mailTheme, true);
    $plates->addFolder('layout', APP_PATH . sprintf('/themes/%s/layout', $path));
    $plates->addFolder('partialsGlobal', APP_PATH . sprintf('/themes/%s/partials/global', $path));
    $plates->addFolder('partialsGlobalDefault', $defaultTheme . '/partials/global');
    $plates->registerFunction('printError', function($error, $label) use($plates) {
        return $plates->render('partialsGlobal::error', ['error' => $error, 'label' => $label]);
    });
    $plates->registerFunction('formToken', function () use ($container) { return $container->get(Csrf::class)->getHiddenInputString(); });
    $plates->registerFunction('formTokenArray', function () use ($container) { return $container->get(Csrf::class)->getTokenAsArray(); });
    // Container blocks (columns) save their children as nested block arrays, so a block template
    // needs to render blocks itself. Resolved on call, not here, so the View can keep depending
    // on this engine. View filters apply to the nested blocks exactly as they do to top level
    // ones, and getView restores the template directory it found, so nesting is safe.
    $plates->registerFunction('contentEditorBlocks', function (array $blocks) use ($container) {
        if (empty($blocks)) {
            return '';
        }
        try {
            return $container->get(\Solidarity\ContentEditor\Contracts\BlockViewInterface::class)->getView($blocks);
        } catch (\Solidarity\ContentEditor\Exceptions\TemplateNotFoundException $e) {
            return '';
        }
    });
    $plates->registerFunction('blockAttributes', function (array $block, string ...$classNames) {
        $additionalData = $block['additionalData'] ?? [];
        $attributes = [];

        $htmlId = trim((string) ($additionalData['htmlId'] ?? ''));
        if ($htmlId !== '') {
            $attributes['id'] = $htmlId;
        }

        $classes = array_filter(array_merge(
            $classNames,
            preg_split('/\s+/', trim((string) ($additionalData['classNames'] ?? '')))
        ));
        if (!empty($classes)) {
            $attributes['class'] = implode(' ', $classes);
        }

        $style = trim((string) ($additionalData['inlineCss'] ?? ''), "; \t\n\r");
        $align = $block['align'] ?? null;
        if (in_array($align, ['left', 'center', 'right'], true)) {
            $style = ($style !== '' ? $style . ';' : '') . 'text-align:' . $align;
        }
        if ($style !== '') {
            $attributes['style'] = $style;
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES));
        }

        return $html;
    });
    // i18n: the default locale (sr) is the source language strings are authored in,
    // so t() is a pass-through there. For any other frontend locale, drive t() through
    // the Translator (SR source -> translated string, falling back to the original).
    // localizeUrl() localizes internal links for the active locale, translating the
    // page slug to its counterpart in the current language (see Locale::localizeUrl).
    $useTranslator = false;
    if (\Solidarity\Core\Environment::isFrontend()) {
        $locale = $container->get(\Solidarity\Frontend\Service\Locale::class);
        $plates->registerFunction('localizeUrl', function (string $url) use ($locale) {
            return $locale->localizeUrl($url);
        });
        // absUrl() forces a leading slash on internal links, so a relative value (e.g. a
        // slug-less nav URL) can't resolve against — and compound onto — the /en/ path.
        $plates->registerFunction('absUrl', function (string $url) {
            return \Solidarity\Frontend\Service\Locale::absolutePath($url);
        });
        if (!$locale->isDefault()) {
            $translator = $container->get(\Skeletor\Translator\Service\Translator::class);
            $translator->setLanguage($locale->current());
            $plates->loadExtension($translator);
            $useTranslator = true;
        }
    } else {
        $plates->registerFunction('localizeUrl', function (string $url) { return $url; });
    }
    if (!$useTranslator) {
        $plates->registerFunction('t', function ($string) { return $string; });
    }

    return $plates;
});

$container->set(Filesystem::class, function() use ($container) {
    $adapter = new League\Flysystem\Local\LocalFilesystemAdapter(APP_PATH);

    return new Filesystem($adapter);
});

$container->set(\FastRoute\Dispatcher::class, function() use ($container) {
    $adminPath = $container->get(Config::class)->adminPath;
    $routeList = require APP_PATH . sprintf('/config/%s/routes.php', \Solidarity\Core\Environment::application());

    /** @var \FastRoute\Dispatcher $dispatcher */
    return FastRoute\simpleDispatcher(
        function (\FastRoute\RouteCollector $r) use ($routeList) {
            foreach ($routeList as $routeDef) {
                $r->addRoute($routeDef[0], $routeDef[1], $routeDef[2]);
            }
        }
    );
});

$container->set(Acl::class, function() use ($container) {
    return new Acl(
        $container->get(ManagerInterface::class),
        $container->get(Config::class),
        require APP_PATH . sprintf('/config/%s/acl.php', \Solidarity\Core\Environment::application()),
        require APP_PATH . sprintf('/config/%s/aclMessages.php', \Solidarity\Core\Environment::application())
    );
});

if (\Solidarity\Core\Environment::isBackend()) {
    $container->set(Skeletor\Core\Middleware\MiddlewareInterface::class, function () use ($container) {
        return new \Skeletor\Core\Middleware\AuthMiddleware(
            $container->get(ManagerInterface::class),
            $container->get(Config::class),
            $container->get(Flash::class),
            $container->get(Acl::class),
            $container->get(\Skeletor\Core\Security\EntityRegistry::class),
            $container->get(AuthorizationService::class),
            true  // Enable voter-based authorization
        );
    });

}

$container->set(Config::class, function() use ($container) {
    $config = new Config(include(APP_PATH . "/config/config.php"), true);
    $config = $config->merge(new Config(include(APP_PATH . "/config/config-local.php"), true));
    if (file_exists(APP_PATH . sprintf("/config/%s/config-local.php", \Solidarity\Core\Environment::application()))) {
        $config = $config->merge(new Config(include(APP_PATH . sprintf("/config/%s/config-local.php", \Solidarity\Core\Environment::application())), true));
    }

    return $config;
});

$container->set(\Skeletor\Core\Action\Web\NotFoundInterface::class, function() use ($container) {
    return $container->get(\Skeletor\Core\Action\Web\NotFound::class);
});

$container->set(Logger::class, function() use ($container) {
    $logger = new \Monolog\Logger($container->get(Config::class)->appName . \Solidarity\Core\Environment::application());
    $date = $container->get(\DateTime::class);
    $logDir = DATA_PATH . '/logs/';
    $logSubDir = $logDir . $date->format('Y') . '-' . $date->format('m');
    $logFile = $logSubDir . '/' . gethostname() . '-'. \Solidarity\Core\Environment::application() .'-' . $date->format('d') . '.log';
    $debugLog = DATA_PATH . '/logs/'. gethostname() . '-'. \Solidarity\Core\Environment::application() .'-debug.log';
    // create dir or file if needed
    if (!is_dir($logDir)) {
        mkdir($logDir);
    }
    if (!is_dir($logSubDir)) {
        mkdir($logSubDir);
    }
    if (!is_file($logFile)) {
        touch($logFile);
    }
    $logger->pushHandler(
        new StreamHandler($debugLog,\Monolog\Level::Info)
    );

    $logger->pushHandler(
        new StreamHandler($logFile, \Monolog\Level::Error, false)
    );
    if (\Solidarity\Core\Environment::isProduction()) {
        $mailHandler = new \Skeletor\Core\Mailer\Service\MonologHandler(\Monolog\Level::Error, true);
        $mailHandler->setMail($container->get(\Skeletor\Core\Mailer\Service\PhpMailer::class));
        $logger->pushHandler($mailHandler);
    } else {
        $logger->pushHandler(new BrowserConsoleHandler());
    }
    ErrorHandler::register($logger);

    return $logger;
});

$container->set(\Redis::class, function() use ($container) {
    $config = $container->get(Config::class);
    $redis = new \Redis();
    foreach ($config->redis->hosts as $host => $port) {
        $redis->connect($host, $port);
    }
    return $redis;
});

$container->set(\DateTime::class, function() use ($container) {
    $dt = new \DateTime('now', new \DateTimeZone($container->get(Config::class)->offsetGet('timezone')));
    return $dt;
});

$container->set(Flash::class, function () use ($container) {
    //session needs to be started for flash
    $container->get(ManagerInterface::class);
    $flash = new Flash();
    $flash->setTemplate(new \Skeletor\Flash\Template\SkeletorTemplate());
    return $flash;
});

$container->set(\MailerSend\MailerSend::class, function() use ($container) {
    // The SDK requires a non-empty api_key just to construct, even though it is
    // only actually used in production (elsewhere mail is caught via SMTP/Mailpit).
    // Fall back to a placeholder so the app boots locally without a real key.
    $apiKey = $container->get(Config::class)->mailer?->server?->mailersend?->apiKey;
    return new \MailerSend\MailerSend(['api_key' => $apiKey ?: 'unused-outside-production']);
});

$container->set(MailerInterface::class, function() use ($container) {
    // Use the app Mailer (extends MailerSendMailer) so its environment guard
    // applies to every path — incl. the framework login/magic-link flow, which
    // resolves MailerInterface. Outside production this catches mail via SMTP
    // (Mailpit) instead of hitting MailerSend.
    return $container->get(\Solidarity\Mailer\Service\Mailer::class);
});

// Authenticatable entity registry — needed by BOTH apps: backend for
// user/delegate login, frontend for the donor magic-link / email verification.
$container->set(EntityRegistry::class, function() use ($container) {
    $registry = new EntityRegistry();
    $registry->register(
        'user',
        \Solidarity\User\Entity\User::class,
        $container->get(\Solidarity\User\Repository\UserRepository::class)
    );
    $registry->register(
        'delegate',
        \Solidarity\Delegate\Entity\Delegate::class,
        $container->get(\Solidarity\Delegate\Repository\DelegateRepository::class)
    );
    $registry->register(
        'donor',
        \Solidarity\Donor\Entity\Donor::class,
        $container->get(\Solidarity\Donor\Repository\DonorRepository::class)
    );

    return $registry;
});

if (\Solidarity\Core\Environment::isBackend()) {
    // Voter-based authorization — uses backend permission config, backend only.
    $container->set(\Skeletor\Core\Security\Authorization\PermissionRegistry::class, function() use ($container) {
        $config = require APP_PATH . '/config/backend/permissions.php';
        return new \Skeletor\Core\Security\Authorization\PermissionRegistry($config);
    });
}

// Login-service dependencies — needed by BOTH apps (the frontend resolves Login
// for the donor magic-link / email-verification flow).
$container->set(\Skeletor\Login\Provider\ProviderInterface::class, function() use ($container) {
    return new \Skeletor\Login\Provider\DbProvider(
        $container->get(\Skeletor\User\Repository\UserRepositoryInterface::class)
    );
});

$container->set(\Skeletor\Login\Validator\ResetPasswordInterface::class, function() use ($container) {
    return $container->get(\Skeletor\Login\Validator\ResetPasswordLoose::class);
});
$container->set(TagAwareAdapter::class, function() use ($container) {
    $config = $container->get(Config::class);

    //@TODO add failover
    $dsn = "redis://" . array_key_first($config->redis->hosts->toArray()) . $config->redis->hosts[0];
    $redisClient = RedisAdapter::createConnection($dsn);
    $redisAdapter = new RedisAdapter($redisClient);
    $cache = new TagAwareAdapter($redisAdapter);

    return $cache;
});

$container->set(EntityManagerInterface::class, function() use ($container) {
    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [
            APP_PATH . "/packages/Delegate/src/Entity",
            APP_PATH . "/packages/Donor/src/Entity",
            APP_PATH . "/packages/Transaction/src/Entity",
            APP_PATH . "/packages/Period/src/Entity",
            APP_PATH . "/packages/Beneficiary/src/Entity",
            APP_PATH . "/packages/School/src/Entity",
            APP_PATH . "/packages/User/src/Entity",
            APP_PATH . "/packages/Page/src/Entity",
            APP_PATH . "/packages/EmailList/src/Entity",
            APP_PATH . "/packages/Post/src/Entity",
            APP_PATH . '/vendor/dj_avolak/skeletor/src/ThemeSettings',
            APP_PATH . "/vendor/dj_avolak/skeletor/src/Image",
            APP_PATH . '/vendor/dj_avolak/skeletor/src/File',
            APP_PATH . "/vendor/dj_avolak/skeletor/src/Image",
            APP_PATH . "/vendor/dj_avolak/skeletor/src/Login",
            // skeletor 6.x logs entity changes through Core\Activity; without this path the
            // Activity entity is unmapped and every write warns instead of recording.
            APP_PATH . "/vendor/dj_avolak/skeletor/src/Core/Activity/Entity",
            APP_PATH . "/vendor/dj_avolak/skeletor/src/Translator",
            APP_PATH . '/vendor/dj_avolak/skeletor/src/ThemeSettings',
        ],
        isDevMode: !\Solidarity\Core\Environment::isProduction(),
    );
    $config->setAutoGenerateProxyClasses(true);
    // symfony/var-exporter 8 removed LazyGhostTrait, so Doctrine's proxy factory throws unless
    // native lazy objects are used. Requires PHP 8.4, and is mandatory in Doctrine ORM 4.
    $config->enableNativeLazyObjects(true);
//    $resultCache = new Symfony\Component\Cache\Adapter\RedisTagAwareAdapter($container->get(\Redis::class));
//    $config->setResultCache($resultCache);
//    $config->setMetadataCache($resultCache);
//    $config->setHydrationCache($resultCache);
    $dbConfig = $container->get(Config::class);
    $connection = \Doctrine\DBAL\DriverManager::getConnection([
        'dbname' => $dbConfig->db->write->name,
        'user' => $dbConfig->db->write->user,
        'password' => $dbConfig->db->write->pass,
        'host' => $dbConfig->db->write->host,
        'driver' => 'pdo_mysql',
    ], $config);
    $eventManager = new \Doctrine\Common\EventManager();
    $config->addCustomStringFunction('DATE', function () {
        return new DoctrineExtensions\Query\Mysql\Date('DATE');
    });
    $config->addCustomStringFunction('YEAR', function () {
        return new DoctrineExtensions\Query\Mysql\Year('YEAR');
    });

    $em = new EntityManager($connection, $config, $eventManager);

    return $em;
});

// Frontend i18n bootstrapping: resolve the locale from the URL prefix and strip it
// before routing (so every language dispatches the same base routes), then expose the
// resolved locale — and its locale-aware main navigation — to every template as shared
// Plates data. Runs before the Engine is first resolved so its translator loads for the
// active locale (the Engine factory reads Locale::isDefault()).
if (\Solidarity\Core\Environment::isFrontend()) {
    $locale = $container->get(\Solidarity\Frontend\Service\Locale::class);
    $locale->detectFromRequest();

    // Locale-aware main navigation: a per-language menu titled "Main Navigation {locale}"
    // (e.g. "Main Navigation en"), falling back to the default "Main Navigation". Each
    // language's menu carries its own labels and (locale-correct) URLs.
    $navigationService = $container->get(\Skeletor\ThemeSettings\Navigation\Service\Navigation::class);
    $mainNavigation = $locale->isDefault()
        ? $navigationService->getByTitle('Main Navigation')
        : ($navigationService->getByTitle('Main Navigation ' . $locale->current())
            ?? $navigationService->getByTitle('Main Navigation'));

    // Analytics: passed as empty strings when unconfigured, and the layout renders the tag
    // only when both are set — so dev and staging never report into the production site.
    $umami = $container->get(Config::class)->offsetExists('umami')
        ? $container->get(Config::class)->offsetGet('umami')
        : null;

    $container->get(Engine::class)->addData([
        'currentLocale'    => $locale->current(),
        'defaultLocale'    => $locale->default(),
        'availableLocales' => $locale->available(),
        'localeAlternates' => $locale->alternates(),
        'mainNavigation'   => $mainNavigation,
        'umamiScript'      => (string) ($umami?->script ?? ''),
        'umamiWebsiteId'   => (string) ($umami?->websiteId ?? ''),
    ]);
}

return $container;