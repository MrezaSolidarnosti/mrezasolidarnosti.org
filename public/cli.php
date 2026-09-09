<?php

define('APP_PATH', __DIR__ . '/..');
define('DATA_PATH', __DIR__ . '/../data');
define('IMAGES_PATH', APP_PATH . '/../frontend/public/images');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', '5400');

include(__DIR__ . "/../vendor/autoload.php");

try {
    if (!isset($argv[1]) || !isset($argv[2])) {
        throw new \Exception('Required arguments are missing: <Controller> <methodName>');
    }
    /* @var \DI\Container $container */
    $container = require APP_PATH . '/config/bootstrap.php';
    $app = new \Skeletor\Core\App\CliSkeletor($container, $container->get(\Psr\Log\LoggerInterface::class));
} catch (\Exception $e) {
    var_dump('could not start application. ' . $e->getMessage());
    exit();
}
try {
    // The WHOLE argv tail, not just the first two. CliSkeletor takes ...$params, drops
    // $params[0] as the cliMap key and hands the rest to the Action as the "params" request
    // attribute — so anything passed here is what an Action can read. Passing only
    // $argv[1], $argv[2] silently truncated every extra argument: an Action that took one
    // (createTransactionsUrgent's `target=`) received nothing and fell back to its default
    // with no error, which is the worst way for an argument to not work.
    //
    // Safe to widen: no Action indexes params positionally, they all scan with in_array()
    // or a prefix match, and CliSkeletor's non-Action branch reads $params[1] — still the
    // second argument either way.
    $app(...array_slice($argv, 1));
} catch (\Exception $e) {
    $app->handleErrors($e);
}
$app->respond();