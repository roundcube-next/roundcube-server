<?php

/**
 * Test class for basic JMAP requests
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

namespace Roundcube\Server;

use Sabre\HTTP;
use Roundcube\JMAP;

class SimpleJMAPTest extends \PHPUnit_Framework_TestCase
{
    protected $server;
    protected $session;

    public function setUp()
    {
        $app = App::getInstance();

        $this->server = $app->get('Server\Controller');
        $this->server->sapi = new \Mock\SapiMock();
        $this->server->httpResponse = new JMAP\Response();
        $this->server->debugExceptions = true;

        // fake authenticated session
        $this->session = $app->get('Session');
        $this->session->start();
        $this->session->set('Auth\authenticated', time());
    }

    protected function sendRequest($body, $path = '/jmap', $headers = [])
    {
        if (!is_string($body))
            $body = json_encode($body);

        $request = new HTTP\Request('POST', $path, $headers, $body);
        $request->setHeader('Authorization', $this->session->key);

        $this->server->httpRequest = $request;
        $this->server->process();

        return $this->server->httpResponse;
    }

    public function testGetFoo()
    {
        $jmap = [ [ "getFoo", [], "#1" ] ];
        $response = $this->sendRequest($jmap);
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('OK', $response->getStatusText());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        
        $this->assertTrue(is_array($jsondata));
        $this->assertEquals(1, count($jsondata));
        $this->assertTrue(is_array($jsondata[0]));
        $this->assertEquals(['error', ['type'=>'unknownMethod'], '#1'], $jsondata[0]);
    }

    public function testJmapErrorEvent()
    {
        $this->server->on('jmap:error', function($args) {
            // replace result with a valid response
            $args['result'] = ['foo', ['hooked' => true]];
        });

        $response = $this->sendRequest([ [ "getFoo", [], "#1" ] ]);

        $jsondata = json_decode($response->getBody(), true);
        $this->assertTrue(is_array($jsondata));
        $this->assertEquals(1, count($jsondata));
        $this->assertEquals(['foo', ['hooked'=>true], '#1'], $jsondata[0]);
    }
}