<?php

/**
 * Define routes here.
 *
 * Routes follow this format:
 *
 * [METHOD, ROUTE, CALLABLE] or
 * [METHOD, ROUTE, [Class => method]]
 *
 * When controller is used without method (as string), it needs to have a magic __invoke method defined.
 *
 * Routes can use optional segments and regular expressions. See nikic/fastroute
 */
//@TODO find a proper way to use adminPath
/**
 * @var $adminPath string secret path to admin
 */

use Skeletor\File\Controller\FileController;
use Skeletor\Image\Controller\ImageController;
use Skeletor\ThemeSettings\Controller\ThemeSettingsController;
use Skeletor\ThemeSettings\Navigation\Controller\NavigationController;
use Skeletor\ThemeSettings\SocialLinks\Controller\SocialLinksController;
use Solidarity\Backend\Controller\PageController;

return [
    // backend
    [['GET'], '/', \Solidarity\Backend\Action\Index::class],
    // Straight to the framework controller: it reads the entity type from the session and
    // returns the visitor to the door they came in through (config: loginUrls).
    [['GET'], '/login/logout', [\Solidarity\Backend\Controller\LoginController::class, 'logOut']],
    [['GET'], '/createTransactions', \Solidarity\Backend\Action\CreateTransaction::class],
    [['GET'], '/statistics', \Solidarity\Backend\Action\Statistics::class],
    // One route for every kind of account. The framework controller checks {entityType}
    // against the entity registry, so an unregistered type is refused rather than quietly
    // treated as a staff login. Adding an entity type is a registry entry, not a controller.
    [['GET', 'POST'], '/login/{entityType}/{action}[/{token}]', \Solidarity\Backend\Controller\LoginController::class],
    [['GET', 'POST'], '/image/{action}[/{id}]', \Skeletor\Image\Controller\ImageController::class],
    [['GET', 'POST'], '/theme/{action}', ThemeSettingsController::class],
    [['POST', 'GET'], '/navigation/{action}[/{id}]', NavigationController::class],
    [['POST', 'GET'], '/social/{action}[/{id}]', SocialLinksController::class],
    [['GET', 'POST'], '/file/{action}[/{id}]', FileController::class],
    [['GET', 'POST'], '/user/{action}[/{id}]', \Solidarity\Backend\Controller\UserController::class],
    [['GET', 'POST'], '/donor/{action}[/{id}]', \Solidarity\Backend\Controller\DonorController::class],
    [['GET', 'POST'], '/delegate/{action}[/{id}]', \Solidarity\Backend\Controller\DelegateController::class],
    [['GET', 'POST'], '/period/{action}[/{id}]', \Solidarity\Backend\Controller\PeriodController::class],
    [['GET', 'POST'], '/beneficiary/{action}[/{id}]', \Solidarity\Backend\Controller\BeneficiaryController::class],
    [['GET', 'POST'], '/transaction/{action}[/{id}]', \Solidarity\Backend\Controller\TransactionController::class],
    [['GET', 'POST'], '/translator/{action}[/{id}]', \Skeletor\Translator\Controller\TranslatorController::class],
    [['GET', 'POST'], '/school/{action}[/{id}]', \Solidarity\Backend\Controller\SchoolController::class],
    [['GET', 'POST'], '/schoolType/{action}[/{id}]', \Solidarity\Backend\Controller\SchoolTypeController::class],
    [['GET', 'POST'], '/city/{action}[/{id}]', \Solidarity\Backend\Controller\CityController::class],
    [['GET', 'POST'], '/emails/{action}[/{id}]', \Solidarity\Backend\Controller\EmailListController::class],
    [['GET', 'POST'], '/page/{action}[/{id}]', PageController::class],
    [['GET', 'POST'], '/post/{action}[/{id}]', \Solidarity\Backend\Controller\PostController::class],
];
