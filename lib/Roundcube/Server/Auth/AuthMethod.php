<?php

/**
 * Entity class specifying a supported authentication method
 *
 * This file is part of the Roundcube server suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2016, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube\Server\Auth;

/**
 * The AuthMethod entity specifying a supported authentication method
 */
class AuthMethod implements \JsonSerializable
{
    public $type = null;

    public $properties = [];

    public function __construct($type, array $props = null)
    {
        $this->type = $type;

        if (!empty($props)) {
            $this->properties = $props;
        }
    }
    
    /**
     * Specify data which should be serialized to JSON
     *
     * Implements the JsonSerializable::jsonSerialize interface method
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        return ['type' => $this->type] + $this->properties;
    }
}