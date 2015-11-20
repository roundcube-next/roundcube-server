<?php

/**
 * Model class representing the identity of an authenticated user
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

namespace Roundcube\Server\Auth;

/**
 * Identity of an authenticated user
 */
class AuthenticatedIdentity implements \Serializable
{
    protected $properties = [];

    public function __construct(array $props = null)
    {
        if (!empty($props))
            $this->properties = $props;
    }

    public function serialize()
    {
        return json_encode($this->properties);
    }

    public function unserialize($serialized)
    {
        $this->properties = json_decode($serialized, true);
    }

    /**
     * PHP magic getter
     */
    public function __get($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * PHP magic setter
     */
    public function __set($name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * PHP magic isset check
     */
    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }
}