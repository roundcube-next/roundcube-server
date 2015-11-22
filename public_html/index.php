<?php

/**
 * Roundcube Webmail HTTP server endpoint
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

define('ROUNDCUBE_INSTALL_ROOT', realpath(__DIR__ . '/..'));

if (!defined('ROUNDCUBE_CONFIG_DIR')) {
    define('ROUNDCUBE_CONFIG_DIR', ROUNDCUBE_INSTALL_ROOT . 'config');
}

if (!defined('ROUNDCUBE_LOCALIZATION_DIR')) {
    define('ROUNDCUBE_LOCALIZATION_DIR', ROUNDCUBE_INSTALL_ROOT . 'locale/');
}

if (!defined('ROUNDCUBE_ENV')) {
    define('ROUNDCUBE_ENV', 'prod');
}

// use composer's autoloader for both dependencies and local lib
$loader = require_once(ROUNDCUBE_INSTALL_ROOT . '/vendor/autoload.php');
$loader->set('Roundcube', [ROUNDCUBE_INSTALL_ROOT . '/lib']);  // register Roundcube namespace

use \Roundcube\Logger;
use \Roundcube\Server;

// create server app instance
$app = Server\App::getInstance(ROUNDCUBE_ENV);

$log = Logger::get('server');

$server = $app->get('Server\Controller');
$server->httpRequest->setBaseUrl(preg_replace('![^/]+$!', '', strval(isset($_SERVER['REDIRECT_SCRIPT_URL']) ? $_SERVER['REDIRECT_SCRIPT_URL'] : $_SERVER['SCRIPT_NAME'])));

// attach debug logger
$server->on('process:before', function($e) use ($log) {
    $log->debug('process:before', $e);
});
$server->on('jmap:query', function($e) use ($log) {
    $log->debug('jmap:query', $e);
});
$server->on('process:after', function($e) use ($log) {
    $log->debug('process:after', $e);
});

// process request
$server->process();


