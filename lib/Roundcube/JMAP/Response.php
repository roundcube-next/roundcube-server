<?php

/**
 * Roundcube Server JMAP HTTP response
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

use Sabre\HTTP;

/**
 * Extended class representing a JMAP over HTTP response.
 *
 */
class Response extends HTTP\Response
{
    protected $jmap_responses = array();

    /**
     * Creates the response object
     *
     * @param string|int $status
     * @param array $headers
     * @param resource $body
     * @return void
     */
    function __construct($status = null, array $headers = null, $body = null)
    {
        parent::__construct($status, $headers, $body);

        // a JMAP response is always application/json
        $this->setHeader('Content-Type', 'application/json');
    }

    /**
     *
     */
    public function addResponse($name, $args, $id)
    {
        // make sure the response 
        if (!is_array($args) && !is_object($args))
            $args = new \stdClass;

        $this->jmap_responses[] = [ $name, $args, $id ];
    }

    /**
     * Returns the message body, as it's internal representation.
     *
     * This could be either a string or a stream.
     *
     * @return resource|string
     */
    public function getBody()
    {
        return json_encode($this->jmap_responses);
    }
}
