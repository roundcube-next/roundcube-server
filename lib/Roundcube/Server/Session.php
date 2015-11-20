<?php

/**
 * Roundcube server session abstraction class
 *
 * This file is part of the Roundcube server suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube\Server;

/**
 * Session abstraction class
 */
class Session
{
    protected $config;
    protected $key;
    protected $start;
    protected $changed;
    protected $vars = [];
    protected $gc_to_run  = 0;
    protected $gc_handlers = [];

    /**
     * Factory, returns driver-specific instance of the class
     *
     * @param object $sm Service manager instance
     * @return object \Roundcube\Session\Storage
     */
    public static function factory($sm = null)
    {
        // default config
        $config = ['handler' => 'Roundcube\Server\Session\DefaultHandler'];

        // we have got a service manager instance passed as argument
        if (is_object($sm) && method_exists($sm, 'has') && method_exists($sm, 'get')) {
            if ($sm->has('Config')) {
                $conf = $sm->get('Config');
                $config = ((array)$conf->get('session', [])) + $config;
            }
        }

        // create instance
        return new Session($config);
    }

    /**
     * @param Object $config
     */
    public function __construct($config = [])
    {
        $this->config = $config;

        // disable session cookies to be set and used
        ini_set('session.use_cookies', 0);
        ini_set('session.use_only_cookies', 0);
        ini_set('session.use_trans_sid', 0);

        // register a custom save handler
        if (isset($config['handler'])) {
            $handlerclass = $config['handler'];
            if (class_exists($handlerclass)) {
                $handler = new $handlerclass($this);
                session_set_save_handler($handler, true);
            }
            else {
                throw new \RuntimeException("Unknown session save handler class $handlerclass");
            }
        }

        // register script shutdown handler
        register_shutdown_function([$this,'gcShutdown']);
    }

    /**
     * Wrapper for session_start()
     */
    public function start($id = null)
    {
        if (!$this->isStarted()) {
            // set session ID
            if (!empty($id)) {
                session_id($id);
            }

            // do not actually start the session in CLI mode
            if (php_sapi_name() !== 'cli') {
                session_start();
            }
            else if (empty($id)) {
                session_id(md5(uniqid(mt_rand())));
            }
        }

        $this->key   = session_id();
        $this->start = microtime(true);
        $this->vars  = &$_SESSION;
    }

    /**
     * Check if session was already started
     */
    public function isStarted()
    {
        return session_status() === PHP_SESSION_ACTIVE || !empty($this->start);
    }

    /**
     * Wrapper for session_write_close()
     */
    public function writeClose()
    {
        if (php_sapi_name() !== 'cli')
            session_start();

        // write_close() is called on script shutdown
        // execute cleanup functionality if enabled by session gc handler
        // we do this after closing the session for better performance
        $this->gcShutdown();
    }

    /**
     * Schedule garbage collector routines for execution on script shutdown
     */
    public function gc($maxlifetime)
    {
        // move gc execution to the script shutdown function
        return $this->gc_to_run = $maxlifetime;
    }

    /**
     * Register additional garbage collector functions
     *
     * @param mixed Callback function
     */
    public function registerGcHandler($func)
    {
        foreach ($this->gc_handlers as $handler) {
            if ($handler == $func) {
                return;
            }
        }

        $this->gc_handlers[] = $func;
    }

    /**
     * Garbage collector handler to run on script shutdown
     */
    public function gcShutdown()
    {
        if ($this->gc_to_run) {
            foreach ($this->gc_handlers as $fct) {
                call_user_func($fct, $this->gc_to_run);
            }
        }

        $this->gc_to_run = 0;
    }
 
    /**
     * Getter for a specific session variable
     */
    public function get($name, $default = null)
    {
        // lazy start
        if (!$this->isStarted()) {
            $this->start();
        }

        return isset($this->vars[$name]) ? $this->vars[$name] : $default;
    }

    /**
     * Setter for a specific session variable
     */
    public function set($name, $value)
    {
        // lazy start
        if (!$this->isStarted()) {
            $this->start();
        }

        $this->vars[$name] = $value;
        $this->changed = true;
    }

    /**
     * Removes a specific variable from the session
     */
    public function remove($name)
    {
        if (!$this->isStarted()) {
            return;  // nothing to do
        }

        unset($this->vars[$name]);
        $this->changed = true;
    }

    /**
     * Destroys the session entirely
     *
     * @return bool
     * @see http://php.net/manual/en/function.session-destroy.php
     */
    public function destroy()
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        session_unset();
        $this->changed = false;
        return session_destroy();
    }

    /**
     * Regenerates and replaces the current session id
     *
     * @return bool True if regeneration worked, false if not.
     */
    public function regenerateId($delete_old_session = false)
    {
        $result = session_regenerate_id($delete_old_session);

        // do it ourselves (probably in cli-mode)
        if (!$result) {
            session_id(md5(uniqid(mt_rand())));
            $result = true;
        }
        $this->key = session_id();
        $this->changed = false;
        return $result;
    }

    /**
     * PHP magic getter
     */
    public function __get($name)
    {
        if (property_exists($this, $name))
            return $this->$name;

        return $this->get($name);
    }

    /**
     * PHP magic setter
     */
    public function __set($name, $value)
    {
        if (!property_exists($this, $name))
            $this->set($name, $value);
    }

    /**
     * PHP magic isset check
     */
    public function __isset($name)
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        return isset($this->vars[$name]);
    }
 
}
