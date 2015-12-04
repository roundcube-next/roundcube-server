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

$server = $app->get('Server\Controller');

if (php_sapi_name() !== 'cli-server' && isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['SCRIPT_FILENAME']))
    $server->httpRequest->setBaseUrl(substr(dirname($_SERVER['SCRIPT_FILENAME']), strlen($_SERVER['DOCUMENT_ROOT'])) . '/');

// attach debug logger
$server->on('process:before', function($e) {
    $request = $e['request'];
    $request->setBody($request->getBodyAsString());  // read stdin as string and write back
    Logger::get('http')->debug('process:before', ['request' => strval($request)]);
});

$server->on('process:after', function($e) {
    Logger::get('http')->debug('process:after', ['response' => strval($e['response'])]);
});

foreach (['jmap:auth:init','jmap:auth:more','jmap:auth:continue','jmap:query','jmap:response'] as $eventname) {
    $server->on($eventname, function($e) use ($eventname) {
        Logger::get('jmap')->debug($eventname, $e);
    });
}

// process request
$server->process();


