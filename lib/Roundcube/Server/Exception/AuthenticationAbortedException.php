<?php

/**
 * Authentication aborted exception
 *
 * Thrown if the authentication process should be aborted and
 * the request being rejected with an authentication failed error.
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

namespace Roundcube\Server\Exception;

class AuthenticationAbortedException extends \RuntimeException
{
    
}
