<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$appBase = dirname(__DIR__, 2).'/ivory-accounts';

if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBase.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $appBase.'/bootstrap/app.php';
$app->handleRequest(Request::capture());
