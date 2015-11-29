<?php

/**
 * Roundcube Server JMAP provider base class
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

namespace Roundcube\JMAP;

use Roundcube\Server\Controller;
use Roundcube\Server\Processor\JMAP;

/**
 * Absract class representing a JMAP provider/account
 */
abstract class Provider implements ProviderInterface
{
    protected $controller;

    protected $processor;

    /**
     * Initialize provider with Processor and Controller instances
     */
    public function init(JMAP $processor, Controller $controller)
    {
        $this->processor = $processor;
        $this->controller = $controller;
    }

}
