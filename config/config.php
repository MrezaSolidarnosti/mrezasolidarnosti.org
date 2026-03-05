<?php

date_default_timezone_set('Europe/Belgrade');

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
    'mailer' => [
        'from' => 'noreply@mrezasolidarnosti.org',
        'fromName' => 'Mreža Solidarnosti',
        'recipients' => [
            'errorNotice' => [
                'djavolak@mail.ru',
            ],
            'general' => [
                'djavolak@mail.ru',
            ],
        ],
        'server' => [],
    ],
    'captcha' => [
        'siteKey' => '',
    ],
    'cliMap' =>  [
        'test' => \Solidarity\Backend\Action\Index::class,
        'donor' => \Solidarity\Backend\Controller\DonorController::class,
        'delegate' => \Solidarity\Backend\Controller\DelegateController::class,
        'educator' => \Solidarity\Backend\Controller\EducatorController::class,
        'educatorImport' => \Solidarity\Backend\Controller\EducatorImportController::class,
        'transactionImport' => \Solidarity\Backend\Controller\TransactionImportController::class,
        'transaction' => \Solidarity\Backend\Controller\TransactionController::class
    ],

);

