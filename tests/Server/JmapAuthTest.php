<?php

/**
 * Test class for JMAP authentication
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

class JmapAuthTest extends \PHPUnit_Framework_TestCase
{
    protected $server;

    public function setUp()
    {
        $app = App::getInstance();

        $this->server = $app->get('Server\Controller');
        $this->server->sapi = new \Mock\SapiMock();
        $this->server->debugExceptions = true;

        // register a Mock JMAP auth provider
        $config = $app->get('Config');
        $config->set('auth', ['providers' => ['Mock\JmapAuthProviderMock']]);
        $this->server->addProcessor(new Processor\Auth());
    }

    protected function sendRequest($body, $path = '/auth', $headers = [])
    {
        if (!is_string($body) && !empty($body))
            $body = json_encode($body);

        $request = new HTTP\Request($body ? 'POST' : 'GET', $path, $headers, $body);

        $this->server->httpRequest = $request;
        $this->server->process();

        return $this->server->httpResponse;
    }

    public function testUnauthorized()
    {
        $response = $this->sendRequest(null, '/auth');
        $this->assertEquals(401, $response->getStatus());
        $this->assertEquals('X-JMAP', $response->getHeader('WWW-Authenticate'));
    }

    public function testAuth()
    {
        // initial auth step
        $data = [ 'username' => 'john.doe', 'clientName' => 'PHPUnit/JmapAuthTest', 'clientVersion' => '0.0.1', 'deviceName' => 'CLI' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('OK', $response->getStatusText());
        $this->assertEquals('application/json', $response->getHeader('content-type'));

        $this->assertTrue(is_array($jsondata));
        $this->assertTrue(is_array($jsondata['methods']));
        $this->assertArrayHasKey('loginId', $jsondata);

        // secondary auth step
        $method = $jsondata['methods'][0]['type'];
        $data = [ 'loginId' => $jsondata['loginId'], 'type' => $method, 'value' => '123456' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(201, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertArrayHasKey('accessToken', $jsondata);
        $this->assertArrayHasKey('apiUrl', $jsondata);
        $this->assertArrayHasKey('username', $jsondata);
        $this->assertEquals('john.doe', $jsondata['username']);
    }

    public function testRefetchUrls()
    {
        // fake authenticated session
        $session = App::getInstance()->get('Session');
        $session->start();
        $session->set('Auth\authenticated', time());
        $session->set('Auth\identity', new Auth\AuthenticatedIdentity(['username' => 'test']));

        $response = $this->sendRequest(null, '/auth', ['Authorization' => 'X-JMAP ' . $session->key]);
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertArrayHasKey('apiUrl', $jsondata);
    }
}