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
 * Interface defining a JMAP provider/account
 */
interface ProviderInterface
{
    /**
     * Getter for the (primary) account ID this provider belongs to
     *
     * @return string
     */
    public function getAccountID();

    /**
     * Getter for the list of accounts this provider is connected with
     *
     * @return array
     */
    public function getAccounts();

    /**
     * Getter for the list of JMAP methods this provider supports
     *
     * @return array
     */
    public function getMethods();

    /**
     * Getter for the list of services this provider supports
     *
     * These can be well-known services like 'Mail', 'Contacts', 'Calendars'
     * or customer types only supported by client plugins.
     *
     * @return array
     */
    public function getServices();
}
