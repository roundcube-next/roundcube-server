<?php

/**
 * Roundcube Session storage driver using the native PHP session handler
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

namespace Roundcube\Server\Session;

/**
 * Default session handler extending the native PHP session handler
 */
class DefaultHandler extends \SessionHandler
{
    protected $wrapper;

    /**
     * Default constructor
     */
    public function __construct(\Roundcube\Server\Session $wrapper)
    {
        $this->wrapper = $wrapper;
    }

    /**
     * Wrapper for garbage collection
     */
    public function gc($maxlifetime)
    {
        // notify wrapper about garbage collection call
        $this->wrapper->gc($maxlifetime);
        return parent::gc($maxlifetime);
    }

}