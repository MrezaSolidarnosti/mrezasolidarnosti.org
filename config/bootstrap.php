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
use Solidarity\Backend\Blocks\BlockFilters\About as AboutFilter;
use Solidarity\Backend\Blocks\BlockFilters\Banner as BannerFilter;
use Solidarity\Backend\Blocks\BlockFilters\Blog as BlogFilter;
use Solidarity\Backend\Blocks\BlockFilters\Connect as ConnectFilter;
use Solidarity\Backend\Blocks\BlockFilters\Contactcards as ContactcardsFilter;
use Solidarity\Backend\Blocks\BlockFilters\Ctabanner as CtabannerFilter;
use Solidarity\Backend\Blocks\BlockFilters\Direction as DirectionFilter;
use Solidarity\Backend\Blocks\BlockFilters\Donate as DonateFilter;
use Solidarity\Backend\Blocks\BlockFilters\Faq as FaqFilter;
use Solidarity\Backend\Blocks\BlockFilters\Find as FindFilter;
use Solidarity\Backend\Blocks\BlockFilters\HeroStats as HeroStatsFilter;
use Solidarity\Backend\Blocks\BlockFilters\Herotext as HerotextFilter;
use Solidarity\Backend\Blocks\BlockFilters\Howitworks as HowitworksFilter;
use Solidarity\Backend\Blocks\BlockFilters\Howitworkstimeline as HowitworkstimelineFilter;
use Solidarity\Backend\Blocks\BlockFilters\Instructionsintro as InstructionsintroFilter;
use Solidarity\Backend\Blocks\BlockFilters\Instructionstable as InstructionstableFilter;
use Solidarity\Backend\Blocks\BlockFilters\Login as LoginFilter;
use Solidarity\Backend\Blocks\BlockFilters\Loginsuccess as LoginsuccessFilter;
use Solidarity\Backend\Blocks\BlockFilters\Profiledata as ProfiledataFilter;
use Solidarity\Backend\Blocks\BlockFilters\Projectsdisplay as ProjectsdisplayFilter;
use Solidarity\Backend\Blocks\BlockFilters\Registerconfirmemail as RegisterconfirmemailFilter;
use Solidarity\Backend\Blocks\BlockFilters\Registerform as RegisterformFilter;
use Solidarity\Backend\Blocks\BlockFilters\Registersuccessbox as RegistersuccessboxFilter;
use Solidarity\Backend\Blocks\BlockFilters\Sidebyside as SidebysideFilter;
use Solidarity\Backend\Blocks\BlockFilters\Testimonials as TestimonialsFilter;
use Solidarity\Backend\Blocks\BlockFilters\Threepillars as ThreepillarsFilter;
use Solidarity\Backend\Blocks\BlockFilters\Valuecards as ValuecardsFilter;
use Solidarity\Backend\Blocks\BlockFilters\Whotocall as WhotocallFilter;
use Solidarity\Backend\Blocks\BlockFilters\Whywearedifferent as WhywearedifferentFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\Blog as BlogViewFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\Donate as DonateViewFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\HeroStats as HeroStatsViewFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\Instructionsintro as InstructionsintroViewFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\Profiledata as ProfiledataViewFilter;
use Solidarity\Backend\Blocks\BlockViewFilters\ImageData as ImageDataViewFilter;
use Skeletor\ContentEditor\BlockFilters\Accordion;
use Skeletor\ContentEditor\BlockFilters\Chart;
use Skeletor\ContentEditor\BlockFilters\Columns;
use Skeletor\ContentEditor\BlockFilters\Divider;
use Skeletor\ContentEditor\BlockFilters\Embed;
use Skeletor\ContentEditor\BlockFilters\Footnotes;
use Skeletor\ContentEditor\BlockFilters\Gallery;
use Skeletor\ContentEditor\BlockFilters\Heading;
use Skeletor\ContentEditor\BlockFilters\HeadingFive;
use Skeletor\ContentEditor\BlockFilters\HeadingFour;
use Skeletor\ContentEditor\BlockFilters\HeadingSix;
use Skeletor\ContentEditor\BlockFilters\HeadingThree;
use Skeletor\ContentEditor\BlockFilters\HeadingTwo;
use Skeletor\ContentEditor\BlockFilters\Html;
use Skeletor\ContentEditor\BlockFilters\OrderedList;
use Skeletor\ContentEditor\BlockFilters\Paragraph;
use Skeletor\ContentEditor\BlockFilters\Quote;
use Skeletor\ContentEditor\BlockFilters\Spacer;
use Skeletor\ContentEditor\BlockFilters\Table;
use Skeletor\ContentEditor\BlockFilters\Tabs;
use Skeletor\ContentEditor\BlockFilters\Timeline;
use Skeletor\ContentEditor\BlockFilters\UnorderedList;
use Skeletor\ContentEditor\BlockFilters\Image as ImageBlockFilter;
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

    $appUrl = (string) (\Solidarity\Core\Environment::isBackend()
        ? ($config->adminUrl ?? $config->baseUrl)
        : $config->baseUrl);
    $sessionConfig = new SessionConfig();
    $sessionConfig->setOptions([
        'remember_me_seconds' => 2592000, //2592000, // 30 * 24 * 60 * 60 = 30 days
        'use_cookies'         => true,
        'cookie_lifetime'     => 30 * 24 * 60 * 60,
        'cookie_httponly'     => true,
        'cookie_samesite'     => 'Lax',
        'cookie_secure'       => str_starts_with($appUrl, 'https://')
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

$container->set(\Skeletor\ContentEditor\Contracts\BlockFilterFactoryInterface::class, function() use ($container) {
    $blockFilterFactory = new \Skeletor\ContentEditor\BlockFilterFactory(
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

    // The page sections. They carry no data the save path has to touch, so each is a
    // pass-through - the filter exists because a block without one is rejected outright.
    $blockFilterFactory->registerBlockFilter('app/about', new AboutFilter());
    $blockFilterFactory->registerBlockFilter('app/banner', new BannerFilter());
    $blockFilterFactory->registerBlockFilter('app/blog', new BlogFilter());
    $blockFilterFactory->registerBlockFilter('app/connect', new ConnectFilter());
    $blockFilterFactory->registerBlockFilter('app/contactcards', new ContactcardsFilter());
    $blockFilterFactory->registerBlockFilter('app/ctabanner', new CtabannerFilter());
    $blockFilterFactory->registerBlockFilter('app/direction', new DirectionFilter());
    $blockFilterFactory->registerBlockFilter('app/donate', new DonateFilter());
    $blockFilterFactory->registerBlockFilter('app/faq', new FaqFilter());
    $blockFilterFactory->registerBlockFilter('app/find', new FindFilter());
    $blockFilterFactory->registerBlockFilter('app/herostats', new HeroStatsFilter());
    $blockFilterFactory->registerBlockFilter('app/herotext', new HerotextFilter());
    $blockFilterFactory->registerBlockFilter('app/howitworks', new HowitworksFilter());
    $blockFilterFactory->registerBlockFilter('app/howitworkstimeline', new HowitworkstimelineFilter());
    $blockFilterFactory->registerBlockFilter('app/instructionsintro', new InstructionsintroFilter());
    $blockFilterFactory->registerBlockFilter('app/instructionstable', new InstructionstableFilter());
    $blockFilterFactory->registerBlockFilter('app/login', new LoginFilter());
    $blockFilterFactory->registerBlockFilter('app/loginsuccess', new LoginsuccessFilter());
    $blockFilterFactory->registerBlockFilter('app/profiledata', new ProfiledataFilter());
    $blockFilterFactory->registerBlockFilter('app/projectsdisplay', new ProjectsdisplayFilter());
    $blockFilterFactory->registerBlockFilter('app/registerconfirmemail', new RegisterconfirmemailFilter());
    $blockFilterFactory->registerBlockFilter('app/registerform', new RegisterformFilter());
    $blockFilterFactory->registerBlockFilter('app/registersuccessbox', new RegistersuccessboxFilter());
    $blockFilterFactory->registerBlockFilter('app/sidebyside', new SidebysideFilter());
    $blockFilterFactory->registerBlockFilter('app/testimonials', new TestimonialsFilter());
    $blockFilterFactory->registerBlockFilter('app/threepillars', new ThreepillarsFilter());
    $blockFilterFactory->registerBlockFilter('app/valuecards', new ValuecardsFilter());
    $blockFilterFactory->registerBlockFilter('app/whotocall', new WhotocallFilter());
    $blockFilterFactory->registerBlockFilter('app/whywearedifferent', new WhywearedifferentFilter());
    return $blockFilterFactory;
});

$container->set(\Skeletor\ContentEditor\Contracts\ContentEditorFilterInterface::class, function() use ($container) {
    return $container->get(\Skeletor\ContentEditor\Filter::class);
});

// Renders core/* editor blocks (posts) from themes/frontend/contentEditor,
// e.g. the core/paragraph block from contentEditor/core/paragraph.php.
$container->set(\Skeletor\ContentEditor\Contracts\BlockViewInterface::class, function() use ($container) {
    $view = new \Skeletor\ContentEditor\View(
        $container->get(Engine::class),
        APP_PATH . '/themes/frontend/contentEditor'
    );

    $view->registerViewFilter('core/image', new \Skeletor\ContentEditor\BlockViewFilters\Image(
        $container->get(Image::class)
    ));

    $view->registerViewFilter('core/gallery', new \Skeletor\ContentEditor\BlockViewFilters\Gallery(
        $container->get(Image::class)
    ));

    $view->registerViewFilter('core/embed', new \Skeletor\ContentEditor\BlockViewFilters\Embed());

    // The page sections that need more than their stored data: posts, donor session state and
    // the live donation figures. Ported from the old page builder - same classes, registered
    // under the new block names.
    $view->registerViewFilter('app/blog', new BlogViewFilter(
        $container->get(\Solidarity\Post\Service\Post::class)
    ));

    $view->registerViewFilter('app/herostats', new HeroStatsViewFilter(
        $container->get(\Solidarity\Donor\Service\Donor::class),
        $container->get(\Solidarity\Beneficiary\Service\Beneficiary::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class),
        $container->get(Config::class)
    ));

    $view->registerViewFilter('app/profiledata', new ProfiledataViewFilter(
        $container->get(\Solidarity\Frontend\Service\Session::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    $view->registerViewFilter('app/donate', new DonateViewFilter(
        $container->get(\Solidarity\Frontend\Service\Session::class),
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    $view->registerViewFilter('app/instructionsintro', new InstructionsintroViewFilter(
        $container->get(\Solidarity\Transaction\Service\Transaction::class)
    ));

    // Sections whose templates print an image alt. The old editor's parsers baked it into the
    // block on save; these resolve it on render instead, so correcting an alt in the media
    // library reaches pages that were saved before the correction.
    $view->registerViewFilter('app/howitworks', new ImageDataViewFilter(
        $container->get(Image::class), ['imageId' => 'alt']
    ));

    $view->registerViewFilter('app/threepillars', new ImageDataViewFilter(
        $container->get(Image::class),
        ['imageDesktopId' => 'imageDesktopAlt', 'imageMobileId' => 'imageMobileAlt']
    ));

    $view->registerViewFilter('app/contactcards', new ImageDataViewFilter(
        $container->get(Image::class), [], ['cards' => ['imageId' => 'alt']]
    ));

    $view->registerViewFilter('app/valuecards', new ImageDataViewFilter(
        $container->get(Image::class), [], ['cards' => ['imageId' => 'alt']]
    ));

    $view->registerViewFilter('app/find', new ImageDataViewFilter(
        $container->get(Image::class), [], ['segments' => ['imageId' => 'alt']]
    ));

    $view->registerViewFilter('app/projectsdisplay', new ImageDataViewFilter(
        $container->get(Image::class), [], ['projects' => ['imageId' => 'alt']]
    ));

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
            return $container->get(\Skeletor\ContentEditor\Contracts\BlockViewInterface::class)->getView($blocks);
        } catch (\Skeletor\ContentEditor\Exceptions\TemplateNotFoundException $e) {
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

    $plates->registerFunction('getVersionPathPrefix', function() use($container) {
        /*
         * @ is used so that browser requests a new folder path for the assets when the version string bumps but the
         * web server returns the correct asset, so when the version string is bumped to 0.0.2, the url that the
         * browser fetches will be /@0.0.2/assets/some-asset.... while the webserver is configured to resolve the asset
         * without the version, in hand busting the cache as the browser thinks it's a new resource
         * */
        $versionString = $container->get(Config::class)->offsetGet('versionString');
        if($versionString) {
            return '/@' . $versionString;
        }
        return '';
    });
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