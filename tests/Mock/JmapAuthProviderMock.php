<?php

/**
 * AuthProviderInterface Mock class
 *
 * This file is part of the Roundcube server test suite
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2015, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Mock;

use Roundcube\Server\Auth\AuthenticatedIdentity;
use Roundcube\Server\Auth\ProviderInterface as AuthProviderInterface;

/**
 * AuthProviderInterface Mock class
 *
 * Emulates an auth provider which always returns success
 */
class JmapAuthProviderMock implements AuthProviderInterface
{
    protected $identity;

    public function getAuthMethods()
    {
        return [ 'password' ];
    }

    public function authenticate(array $data)
    {
        if (!empty($data['username']) && !empty($data['password']) && $data['password'] === '123456') {
            $this->identity = new AuthenticatedIdentity($data);
            return $this->identity;
        }

        return false;
    }

    public function getAccountID()
    {
        return $this->identity->username;
    }
}