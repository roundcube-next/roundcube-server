<?php

/**
 * HTTP Sapi Mock class
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

use Sabre\HTTP;

/**
 * HTTP Sapi Mock class
 *
 * This class emulates sending a HTTP response to the connecting client
 */
class SapiMock extends HTTP\Sapi
{
    static $sent = 0;

    /**
     * Overriding this so nothing is ever echo'd.
     *
     * @return void
     */
    static function sendResponse(HTTP\ResponseInterface $r)
    {
        self::$sent++;
    }

}

