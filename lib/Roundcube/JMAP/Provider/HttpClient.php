<?php

/**
 * Extended HTTP client class from the Sabre HTTP library.
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

namespace Roundcube\JMAP\Provider;

use Sabre\HTTP;

/**
 * Extended HTTP client class from the Sabre HTTP library.
 *
 * This basically overwrites the maxRedirects property to avoid
 * the automatic following of redirects.
 */
class HttpClient extends HTTP\Client
{
    /**
     * We don't want to follow redirects
     */
    protected $maxRedirects = 0;

}
