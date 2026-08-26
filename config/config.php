<?php

date_default_timezone_set('Europe/Belgrade');

const PORTRAIT_600x820 = 'portrait_600x820';

const THUMBNAIL_250x500 = 'portrait_250x500';

const SINGLE_350x150 = 'landscape_350x150';

const SINGLE_350x700 = 'portrait_350x700';

const LANDSCAPE_1200x800 = 'landscape_1200x800';

const LANDSCAPE_1000x667 = 'landscape_1000x667';

const LANDSCAPE_800x533 = 'landscape_800x533';

const LANDSCAPE_600x400 = 'landscape_600x400';

const LANDSCAPE_400x267 = 'landscape_400x267';

const LANDSCAPE_300x200 = 'landscape_300x200';

const LANDSCAPE_250x167 = 'landscape_250x167';

return array(
    'baseUrl' => 'https://solid.djavolak.info',
    'siteName' => 'Mreža Solidarnosti',
    'appName' => 'Mreža Solidarnosti',
    'appType' => '',
    'redirectUri' => '/user/view/',
    'timezone' => 'Europe/Belgrade',
    'adminPath' => '',
    'imageBasePath' => IMAGES_PATH,
    'ignoreTrailingSlash' => true,
    'compileAssets' => false,
    // Frontend i18n. 'default' is served at the URL root (no prefix); every other
    // available locale is served under its own path prefix (e.g. /en/...).
    'locales' => [
        'default' => 'sr',
        'available' => ['sr', 'en'],
    ],
    'mailer' => [
        'from' => 'noreply@mrezasolidarnosti.org',
        'fromName' => 'Mreža Solidarnosti',
        // Outside production, mail is caught here via SMTP (Mailpit)
        'smtp' => [
            'host' => '127.0.0.1',
            'port' => 1025,
        ],
        'recipients' => [
            'errorNotice' => [
                'djavolak@mail.ru',
            ],
            'general' => [
                'djavolak@mail.ru',
            ],
            // Read by MailerSendMailer::sendContactFormMail(). Nothing in this app calls it
            // today, but the framework iterates this key without checking it exists, so
            // wiring up a contact form without adding it here is an immediate fatal.
            'contactForm' => [
                'djavolak@mail.ru',
            ],
        ],
        // Production credentials only, set in config-local.php — see the .dist. Empty here
        // so a missing override is visible rather than inherited from a committed default.
        'server' => [],
    ],
    'captcha' => [
        'siteKey' => '',
    ],
    // Umami analytics. Both empty here on purpose: the tag is only rendered when BOTH are
    // set, so development and staging do not report into the production site's stats. Set
    // them in the production config-local.php — same arrangement the legacy app had via
    // UMAMI_TRACKING_SCRIPT / UMAMI_TRACKING_ID.
    'umami' => [
        'script' => '',
        'websiteId' => '',
    ],
    // Translator JS export: the same generated module is written to both the backend and
    // frontend asset trees for now (deduped later). See TranslationFileExporter.
    'translator' => [
        'jsFilePaths' => [
            APP_PATH . '/public/assets/backend/js/config/translations.js',
            APP_PATH . '/public/assets/frontend/js/config/translations.js',
        ],
        // Only emit these target languages (keeps the file to the sr-tagged JS strings).
        'jsLanguages' => ['sr'],
        // These rows store Serbian in originalString and English in translatedString, so
        // invert on export to key the file by the English string: "Delete": {"sr": "Obriši"}.
        'jsInvert' => true,
    ],
    /**
     * Confirmed money that no longer has transactions behind it.
     *
     * The legacy app deleted donors who had gone inactive, and that cascaded to their
     * transactions. It started while the projects were running a surplus — more donors
     * pledging than requests to fund — which the old app was not built for, so the cleanup ran
     * against people whose confirmed donations had already been paid, counted and published.
     * ~6,400 donor deletions took their confirmed transactions with them.
     *
     * Nothing survives to rebuild them: the cascade logged no row contents, log_entity_change
     * has no Transaction deletes and its diffs never carry `amount`, and log_command_change
     * stores one fixed sentence per run. Both legacy databases were exhausted, as were the
     * SF/msdash dumps (see the two recover* commands).
     *
     * So it is carried here rather than written into the transaction table, where a synthetic
     * row would be indistinguishable from a real donation and would leak into per-donor and
     * per-period views that have to stay evidential.
     *
     * Applied to `confirmedAmount` only — not to counts, not to any per-person or per-period
     * figure. Shown with its note in the backend dashboard; deliberately not on the public
     * site, where the caveat needs more context than a footnote can carry.
     */
    'historicalAdjustment' => [
        'MSP' => [
            'amount' => 90000000,
            'note' => 'Confirmed donations lost when the legacy app cascade-deleted inactive'
                . ' donors (from ~Aug 2025). Amount of 330,000,000 RSD confirmed as at August'
                . ' 2025, publicly reported at the time. Unrecoverable — see Statistics::historicalAdjustment().',
        ],
    ],

    'cliMap' =>  [
        // Cron entry point. CliSkeletor invokes Action classes via __invoke().
        // Run: php public/cli.php createTransactions run   (use "dry" to preview)
        // "dry" allocates for real inside a transaction, prints the full per-donor breakdown
        // and then rolls back, sending no mail — the allocator re-reads its own writes, so a
        // preview that skipped them would be wrong. Flag is "dry", not "commit", because the
        // crontab already passes "run" and must keep meaning a real round.
        'createTransactions' => \Solidarity\Backend\Action\CreateTransaction::class,
        // Expire unpaid instructions past 72h. MUST run immediately before createTransactions
        // so the freed budget is reallocated in the same cycle.
        // Run: php public/cli.php expireInstructions run   (use "dry" to preview)
        'expireInstructions' => \Solidarity\Backend\Action\ExpireInstructions::class,
        // Flag donors whose instructions keep going unpaid, so they stop being allocated to.
        // Runs BETWEEN the two above: after expiry so this round's misses count, before
        // allocation so a donor crossing the line is excluded from this round rather than
        // handed one more instruction. Idempotent — safe to run at any time.
        // Run: php public/cli.php flagDonors run   (use "dry" to preview)
        'flagDonors' => \Solidarity\Backend\Action\FlagNonPayingDonors::class,
        // Clear the Translator's Redis cache after a manual `translation` table edit/import.
        // Run: `php public/cli.php resetTranslationsCache run`   (the 2nd arg is required but ignored)
        // ---- one-shot migration, delete after cutover -------------------------------
        // Run in this order, each `run` (dry) before `commit`:
        //   1. migrateLegacy       — MSP from solid_old; also seeds both projects, creates the
        //                            recovery-only periods and writes data/period_map.csv
        //   2. recoverSfTransactions — data/recovered_sf.csv
        //   3. recoverInstructions   — data/recovered_instructions.csv
        //   4. migrateLegacyMspr     — MSPR from solidmspr_old; needs project 2 to exist and
        //                              matches donors/delegates on email against step 1
        // Then `php public/cli.php migrateLegacy verify`.
        'migrateLegacy' => \Solidarity\Backend\Action\MigrateLegacy::class,
        'recoverSfTransactions' => \Solidarity\Backend\Action\RecoverSfTransactions::class,
        'recoverInstructions' => \Solidarity\Backend\Action\RecoverInstructions::class,
        'migrateLegacyMspr' => \Solidarity\Backend\Action\MigrateLegacyMspr::class,
        // -----------------------------------------------------------------------------
        'resetTranslationsCache' => \Solidarity\Backend\Action\ResetTranslationsCache::class,
        // Regenerate the JS translations module (public/assets/backend/js/config/translations.js)
        // from the `translation` table. Admin edits regenerate it automatically; run this after a
        // manual DB import. Run: `php public/cli.php exportTranslations run`
        'exportTranslations' => \Skeletor\Translator\Action\ExportTranslationsFile::class,
        // Scan a JS asset tree for translate('...') calls and generate an idempotent SQL file
        // (empty translations) to collect them. Run: `php public/cli.php importJsTranslations backend|frontend`
        'importJsTranslations' => \Solidarity\Backend\Action\ImportJsTranslations::class,
    ],
    'cropSizes' => [
        PORTRAIT_600x820 => [600, 820, true],
        LANDSCAPE_1200x800 => [1200,800, true],
        LANDSCAPE_1000x667 => [1000,667, true],
        LANDSCAPE_600x400 => [600,400, true],
        LANDSCAPE_400x267 => [400,267, true],
        LANDSCAPE_300x200 => [300,200, true],
        LANDSCAPE_250x167 => [250,167, true],
        THUMBNAIL_250x500 => [250,125, false],
        SINGLE_350x150 => [350,150, false],
        SINGLE_350x700 => [350, 700, false]
    ]
);

