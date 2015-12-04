<?php

/**
 * Model class to give access to service configuration
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

use Sabre\Event;
use Symfony\Component\Yaml\Yaml;

/**
 * Wrapper class for service configuration
 */
class Config implements Event\EventEmitterInterface
{
    use Event\EventEmitterTrait;

    const STRING = 0;
    const BOOL   = 1;
    const INT    = 2;
    const FLOAT  = 3;

    protected static $instance;

    protected $env = '';
    protected $basedir = '../config';
    protected $data = array();
    protected $valid = false;

    /**
     * Singelton getter
     *
     * @param string Path to load config from
     */
    public static function getInstance($env = '', $dir = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = new Config($env);
            if ($dir) self::$instance->basedir = $dir;
        }

        if (!self::$instance->valid) {
            self::$instance->load('defaults.yaml');
            self::$instance->load('config.yaml');
        }

        return self::$instance;
    }

    /**
     * Default constructor
     */
    function __construct($env = '')
    {
        $this->env = $env;
    }

    /**
     * Load config from the given .yaml file
     */
    protected function load($file, $use_env = true)
    {
        // check for relative path
        if (!is_readable($file) && is_readable($this->basedir . '/' . $file)) {
            $file = $this->basedir . '/' . $file;
        }

        $infile = $this->resolve_path($file, $use_env);

        try {
            $raw = Yaml::parse(file_get_contents($infile));
            $this->register($raw);
            $this->valid = !empty($this->data);
        }
        catch (\Exception $e) {
            $this->emit('error', [['level' => E_USER_ERROR, 'message' => "Failed to parse configuration from $infile", 'reason' => $e->getMessage()]]);
            trigger_error("Failed to parse configuration from $infile: " . $e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * Helper method to resolve the absolute path to the given config file.
     * This also takes the 'env' property into account.
     */
    protected function resolve_path($file, $use_env)
    {
        if ($file[0] != '/' && $this->basedir[0] == '/') {
            $file = realpath($this->basedir . '/' . $file);
        }

        // check if <file>-env.yaml exists
        if ($file && $use_env && !empty($this->env)) {
            $envfile = preg_replace('/\.(yaml|conf)$/', '-' . $this->env . '.\\1', $file);
            if (is_file($envfile))
                return $envfile;
        }

        return $file;
    }

    /**
     * Dump the hierarchical structure of config options into a flat list with keys delimited by dots
     */
    public function register($config, $prefix = '')
    {
        // merge the new config values over existing data
        if (empty($prefix)) {
            $this->data = array_replace_recursive($this->data, $config);
        }
        else if (is_array($config)) {
            $pkey = rtrim($prefix, '.');
            $this->data[$pkey] = array_key_exists($pkey, $this->data) && is_array($this->data[$pkey]) ? array_replace_recursive((array)$this->data[$pkey], $config) : $config;
        }

        foreach ((array)$config as $key => $val) {
            if (is_array($val)) {
                $this->register($val, "$prefix$key.");
            }
            else {
                $this->data[$prefix.$key] = $val;
            }
        }

        // resolve references in config options (e.g. %(foo.bar))
        if (empty($prefix)) {
            array_walk_recursive($this->data, array($this, 'resolve_reference'));
        }
    }

    /**
     * Callback to resolve references in the given config option value
     */
    protected function resolve_reference(&$value, $key)
    {
        if (is_string($value)) {
            $value = preg_replace_callback('/%[({]([\w.]+)[})]/i', array($this, 'replace_reference'), $value);
        }
    }

    /**
     * Callback function to replace the given reference with the read config value
     */
    protected function replace_reference($m)
    {
        return $this->data[$m[1]];
    }

    /**
     * Magic getter for direct read-only access to config options
     */
    public function __get($name)
    {
        return $this->data[$name];
    }

    /**
     * Magic isset check
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Common getter for config options with fallback to default values
     *
     * @param string  Config option name
     * @param mixed   Default value if option isn't set in config
     * @param integer Expected variable type
     * @return mixed Config option value
     */
    public function get($name, $default = null, $type = null)
    {
        switch ($name) {

            default:
                $value = array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }

        // convert value to the requested type
        return $type ? self::convert($value, $type) : $value;
    }

    /**
     * Adjust, override a config option
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;

        if (is_array($value)) {
            $this->register($this->data[$name], $name.'.');
        }
    }

    /**
     * Determines whether we have a valid configuration loaded
     *
     * @return boolean True if valid, False otherwise
     */
    public function valid()
    {
        return !empty($this->data);
    }

    /**
     * Convert the given (string) value to the requested type
     *
     * @param string  Config value
     * @param int     Output type (one of this class constants)
     * @return mixed  The converted value
     */
    public static function convert($value, $type)
    {
        // convert value to the requested type
        switch ($type) {
            case self::INT:
                return intval($value);
            case self::FLOAT:
                return floatval($value);
            case self::BOOL:
                return (bool)preg_match('/^(true|1|on|enabled|yes)$/i', $value);
        }

        return $value;
    }

    /**
     * Shortcut method to convert to a boolean value
     */
    public static function boolean($value)
    {
        return self::convert($value, self::BOOL);
    }

    /**
     * Shortcut method to convert to a integer value
     */
    public static function intval($value)
    {
        return self::convert($value, self::INT);
    }

    /**
     * Shortcut method to convert to a float value
     */
    public static function floatval($value)
    {
        return self::convert($value, self::FLOAT);
    }

    /**
     * Convenience method to check whether a certain value is part of an option (list)
     *
     * @param mixed   Value to compare
     * @param mixed   Config option value
     * @param boolean Treat undefined options as 'match'
     * @return boolean True of the given value is listed in config
     */
    public static function in_array($value, $option, $or_not_set = false)
    {
        // return true if option is not set (means 'allow all')
        if (!isset($option) && $or_not_set) {
            return true;
        }

        return in_array($value, $option);
    }
}

