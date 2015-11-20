<?php

/**
 * Roundcube Server Logging utility
 *
 * This file is part of the Roundcube PHP Utilities library
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube;

use Monolog\Logger as Monologger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\NullHandler;

/**
 * Helper class for creating up Monolog instanced with local configration
 */
class Logger
{
	private static $instances = array();

	/**
	 * Static getter for a Monolog\Logger instance
	 */
	public static function get($name, $level = 0)
	{
		if (!isset(self::$instances[$name]) || ($level && !self::$instances[$name]->isHandling($level))) {
			$logger = new Monologger($name);

			// read log config
			$config = Config::getInstance();
			$identity = $config->get('log.name', 'roundcube');
			$loglevel = $level ?: $config->get('log.level', Monologger::INFO);

			switch ($config->get('log.driver')) {
				case 'file':
					$logdir = Utils::abspath($config->get('log.path', 'logs'), '/');
					$logger->pushHandler(new StreamHandler($logdir . $identity . '.log', $loglevel));
					break;

				case 'syslog':
					$logger->pushHahdler(new SyslogHandler($identity, $config->get('log.facility', 'user'), $loglevel));
					break;

				default:
					// null handler if logging is disabled
					$logger->pushHandler(new NullHandler);
			}

			self::$instances[$name] = $logger;
		}
		
		return self::$instances[$name];
	}

}

