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
abstract class Provider
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

    /**
     * Getter for the (primary) account ID this provider belongs to
     *
     * @return string
     */
    abstract public function getAccountID();

    /**
     * Getter for the list of accounts this provider is connected with
     *
     * @return array
     */
    abstract public function getAccounts();

    /**
     * Getter for the list of JMAP methods this provider supports
     *
     * @return array
     */
    abstract public function getMethods();

    /**
     * Getter for the list of services this provider supports
     *
     * These can be well-known services like 'Mail', 'Contacts', 'Calendars'
     * or customer types only supported by client plugins.
     *
     * @return array
     */
    abstract public function getServices();
}
