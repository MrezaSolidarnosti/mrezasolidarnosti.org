<?php

use Psr\Log\LoggerInterface;
use Skeletor\Core\App\WebSkeletor;

error_reporting(E_ALL);
ini_set('display_errors', 0);
define('APP_PATH', dirname(__DIR__));

include(APP_PATH . "/config/constants.php");
include(APP_PATH . "/vendor/autoload.php");
if (!\Solidarity\Core\Environment::isProduction()) {
    \Tracy\Debugger::enable(false);
}


try {
    /* @var \DI\Container $container */
    $container = require sprintf('%s/config/bootstrap.php', APP_PATH);
    $app = new WebSkeletor($container, $container->get(LoggerInterface::class));
} catch (\Exception $e) {
    if (isset($app) && $app) {
        $app->handleErrors($e);
        exit();
    }
    // @TODO handle better
    echo 'There was an unknown error with application. More info: ' . $e->getMessage() . PHP_EOL;
    echo '********************* Stack trace **********************************' . PHP_EOL;
    var_dump($e->getTrace());
    exit();
}
$app->respond();
