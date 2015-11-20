<?php

/**
 * Interface calss for authentication JMAP providers
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

use Sabre\HTTP\Request;

/**
 * Interface for authentication JMAP providers
 */
interface ProviderInterface
{
    /**
     * List authentication methods this provier supports
     * See http://jmap.io/spec.html#getting-an-access-token
     *
     * @return array
     */
    public function getAuthMethods();

    /**
     * Do authenticate the given request data (username, password, etc.)
     *
     * @param array Data submitted with the authentication request
     * @return mixed Roundcube\Server\Auth\AuthenticatedIdentity on success, false on failure
     */
    public function authenticate(array $request);

}
