<?php

/**
 * Roundcube Server Application container
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

use Roundcube\Config;
use Roundcube\Logger;
use Sabre\Event;

class App
{
    use Event\EventEmitterTrait;

    protected static $instance;

    protected $singletons = [];

    protected $aliases = [];
    protected $aliases_ = [];

    protected $factories = [];

    /**
     * Singleton getter
     *
     * @param string $env The environment to initialize the application
     * @return Roundcube\Server\App
     */
    public static function getInstance($env = null)
    {
        if (!self::$instance) {
            self::$instance = new App($env);
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Default constructor
     */
    public function __construct($env = null)
    {
        // register some basic singleton instances
        $config = Config::getInstance($env, ROUNDCUBE_INSTALL_ROOT . '/config');
        $this->set('Config', $config);

        $this->factories = $config->get('servicemanager.factories', []);

        // register alias names
        foreach ($config->get('servicemanager.aliases', []) as $alias => $classname) {
            $alias = $this->normalizeName($alias);
            $this->aliases_[$alias] = $classname;
            $this->aliases[$alias] = $this->normalizeName($classname);
        }
    }

    /**
     * Initialize after instance was created
     */
    public function init()
    {
        // create controller singleton
        $controller = new Controller();
        $this->set('Server\Controller', $controller);
        $this->setAlias('Controller', 'Server\Controller');

        // set core processors
        $controller->addProcessor(new Processor\Auth());
        $controller->addProcessor(new Processor\JMAP());

        // TODO: load plugins from config
    }

    /**
     * Getter for a singleton instance
     *
     * @param string $name The name of the singleton class
     * @return object
     * @throws Roundcube\Server\Exception\ServiceNotFoundException
     */
    public function get($name)
    {
        $sname = $this->normalizeName($name);

        if (isset($this->aliases_[$sname])) {
            $name = $this->aliases_[$sname];
        }
        if (isset($this->aliases[$sname]) && !isset($this->singletons[$sname])) {
            $sname = $this->aliases[$sname];
        }
        if (isset($this->singletons[$sname])) {
            return $this->singletons[$sname];
        }

        // create the requested singleton from configured factories
        if (isset($this->factories[$name])) {
            $factoryclass = $this->factories[$name];
            if (class_exists($factoryclass)) {
                $factory = new $factoryclass();
                if (is_callable($factory)) {
                    $this->singletons[$sname] = $factoryclass($this);
                }
                else if (method_exists($factory, 'createService')) {
                    $this->singletons[$sname] = $factory->createService($this);
                }
            }
        }

        // create an instace if the given name refers to an existing class
        if (class_exists($name)) {
            // class has a static factory() method
            if (method_exists($name, 'factory')) {
                $this->singletons[$sname] = $name::factory($this);
            }
            else {
                $this->singletons[$sname] = new $name();
            }
            return $this->singletons[$sname];
        }

        throw new Exception\ServiceNotFoundException("No singleton '$sname' registered");
    }

    /**
     * Check if a certain singleton is registered
     *
     * @param string $name The name of the singleton class
     * @return boolean
     */
    public function has($name)
    {
        return isset($this->singletons[$this->normalizeName($name)]);
    }

    /**
     * Setter for a singleton instance
     *
     * @param string $name The name of the class
     * @param object $obj The singleton instance
     */
    public function set($name, $obj)
    {
        $sname = $this->normalizeName($name);
        $this->singletons[$sname] = $obj;
    }

    /**
     * Define an alias name for a singleton class
     */
    public function setAlias($alias, $name)
    {
        $this->aliases[$this->normalizeName($alias)] = $this->normalizeName($name);
    }

    /**
     *
     */
    private function normalizeName($name)
    {
        return str_replace(['\\','-','_','.'], '', strtolower($name));
    }
}