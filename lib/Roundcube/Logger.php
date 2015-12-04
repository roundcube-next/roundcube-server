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
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
use Roundcube\Logger\MultilineFormatter;

/**
 * Helper class for creating up Monolog instanced with local configration
 */
class Logger
{
    protected static $levels = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];
    protected static $instances = [];

    /**
     * Static getter for a Monolog\Logger instance
     */
    public static function get($name)
    {
        if (!isset(self::$instances[$name])) {
            $logger = new Monologger($name);
            self::addHandlers($logger, $name);
            self::$instances[$name] = $logger;
        }

        return self::$instances[$name];
    }

    /**
     * Helper method to register log handlers a to the given
     * Logger instance according to config
     */
    protected static function addHandlers($logger, $name)
    {
        $handlers = 0;
        $config = Config::getInstance();

        foreach ((array)$config->get('log.handlers') as $options) {
            $level   = isset($options['level']) ? $options['level'] : $config->get('log.level', Monologger::INFO);
            $type    = isset($options['type'])  ? $options['type']  : '';
            $handler = null;

            // skip disabled handlers
            if (isset($options['enabled']) && !$options['enabled'])
                continue;

            // skip writer if channel doesn't match
            if (isset($options['channel']) && !self::channelMatch($name, (array)$options['channel']))
                continue;

            // convert log level to a numeric value
            if (!is_numeric($level) && isset(self::$levels[strtolower($level)]))
                $level = self::$levels[strtolower($level)];

            switch ($type) {
                case 'file':
                    $logdir = Utils::abspath(dirname($options['path']), '/');
                    $options['path'] = $logdir . basename($options['path']);
                case 'stream':
                    $handler = new StreamHandler($options['path'], $level);
                    break;

                case 'cli':
                    if (php_sapi_name() === 'cli-server') {
                        $handler = new StreamHandler('php://stdout', $level);
                    }
                    break;

                case 'syslog':
                    $identity = isset($options['identity']) ? $options['identity'] : $config->get('log.name', 'roundcube');
                    $facility = isset($options['facility']) ? $options['facility'] : $config->get('log.facility', 'user');
                    $handler = new SyslogHandler($identity, $facility, $level);
                    break;

                default:
                    // TODO: allow full class names and check if implements Monolog\Handler\HandlerInterface
            }

            if ($handler) {
                self::addFormatter($handler, $options);
                $logger->pushHandler($handler);
                $handlers++;
            }
        }

        // add null handler if no handlers have been added
        if ($handlers === 0)
            $logger->pushHandler(new NullHandler);
    }

    /**
     * Helper method to set the a formatter to the given handler
     * instance according to config.
     */
    protected static function addFormatter($handler, $options)
    {
        $formatter = isset($options['formatter']) ? $options['formatter'] : 'multiline';

        switch ($formatter) {
            case 'line':
                $format = isset($options['format']) ? $options['format'] : null;
                $handler->setFormatter(new LineFormatter($format));
                break;

            case 'json':
                $handler->setFormatter(new JsonFormatter());
                break;

            default:
                $format = isset($options['format']) ? $options['format'] : null;
                $handler->setFormatter(new MultilineFormatter($format));
                break;
        }
    }

    /**
     *
     */
    protected static function channelMatch($name, $channels)
    {
        $match = false;
        foreach ((array)$channels as $channel) {
            if ($channel[0] == '!' || $channel[0] == '~') {
                if ($name == substr($channel, 1))
                    return false;
                else
                    $match = true;
            }
            else if ($name === $channel) {
                return true;
            }
        }

        return $match;
    }
}

