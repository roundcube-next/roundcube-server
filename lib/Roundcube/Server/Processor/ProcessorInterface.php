<?php

/**
 * Roundcube Server HTTP request processor
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

namespace Roundcube\Server\Processor;

use Roundcube\Server\Controller;

/**
 * HTTP request processor interface
 */
interface ProcessorInterface
{
    /**
     * Initialize with the controller instance.
     *
     * This method usually registers routes with callback functions
     * to process incoming requests.
     */
    public function init(Controller $controller);

}