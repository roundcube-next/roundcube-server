<?php

/**
 * Roundcube Server plugin interface class
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

namespace Roundcube\Server\Plugin;

use Roundcube\Server\App;

/**
 *
 */
abstract class AbstractPlugin
{
    protected $app;
    protected $options = [];

    /**
     * Default setup
     *
     * @param object Roundcube\Server\App $app App instance
     * @param array $options Hash array with plugin options from config
     */
    public function setup(App $app, array $options = [])
    {
        $this->app = $app;
        $this->options = $options;
    }
    
    /**
     * Plugin initialization routine
     */
    abstract public function init();

}
