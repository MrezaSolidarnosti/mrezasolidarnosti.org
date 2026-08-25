<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Pure unit tests only need the Composer autoloader. Tests that touch the
 * database/container should build their own fixtures (or extend a future
 * IntegrationTestCase) rather than booting the full app here.
 */

require __DIR__ . '/../vendor/autoload.php';

/**
 * config/constants.php is included only by public/index.php, so anything the tests touch
 * that reads a path constant would fatal on "Undefined constant" rather than fail. These
 * mirror the real values; they are defined defensively so loading the real file first
 * (should the bootstrap ever grow to do that) still wins.
 */
defined('APP_PATH') || define('APP_PATH', dirname(__DIR__));
defined('DATA_PATH') || define('DATA_PATH', dirname(__DIR__) . '/data');
defined('FRONT_ASSET_URL') || define('FRONT_ASSET_URL', '/assets/frontend');
defined('ADMIN_ASSET_URL') || define('ADMIN_ASSET_URL', '/assets/backend');
