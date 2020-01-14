<?php

error_reporting(E_ALL | E_STRICT);

define('APPLICATION_ENV', 'testing');
define('TEST_ROOT', __DIR__);
define('TEST_DATA', TEST_ROOT . '/assets/data');

/**
 * Setup autoloading
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $loader = require __DIR__ . '/../vendor/autoload.php';
}
