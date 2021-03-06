<?php

/**
 * Roundcube Server HTTP request processor exception
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

namespace Roundcube\Server\Exception;

/**
 * HTTP request processor exception
 */
class ProcessorException extends \RuntimeException
{
    protected $status = 500;

    /**
     * Constructor
     */
    function __construct($status, $message, $code = 0, $previous = null)
    {
        $this->status = $status;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Getter for the HTTP status code
     */
    public function getStatusCode()
    {
        return $this->status;
    }

}
