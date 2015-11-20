<?php

/**
 * PHPUnit bootstrapping routine
 *
 * This file is part of the Roundcube server test suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */


date_default_timezone_set('UTC');

ini_set('error_reporting', E_ALL | E_STRICT | E_DEPRECATED);

define('ROUNDCUBE_INSTALL_ROOT', realpath('../'));
define('ROUNDCUBE_ENV', 'test');

// Composer autoloader
$loader = require(ROUNDCUBE_INSTALL_ROOT . '/vendor/autoload.php');
$loader->add('Roundcube\\', ROUNDCUBE_INSTALL_ROOT . '/lib');
$loader->add('Mock\\', __DIR__);

// create server app instance
\Roundcube\Server\App::getInstance(ROUNDCUBE_ENV);

