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

namespace Roundcube\Plugins;

use Sabre\HTTP;
use Roundcube\Server\App;
use Roundcube\Server\Processor;

class NFactorAuthTest extends \PHPUnit_Framework_TestCase
{
    protected $server;
    protected $initialized = false;

    public function setUp()
    {
        if ($this->initialized)
            return;

        $app = App::getInstance();

        // force the plugin to be loaded
        $app->loadPlugin('Roundcube\Plugins\NFactorAuthPlugin', ['password' => 'xxxxxx'], true);

        $this->server = $app->get('Server\Controller');
        $this->server->sapi = new \Mock\SapiMock();
        $this->server->debugExceptions = true;

        // register a Mock JMAP auth provider
        $config = $app->get('Config');
        $config->set('auth', ['providers' => ['Mock\JmapAuthProviderMock']]);
        $this->server->addProcessor(new Processor\Auth());

        $this->initialized = true;
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

    public function testAuthFail()
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
        $this->assertArrayHasKey('continuationToken', $jsondata);

        // auth continue step
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => '123456' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // requires a 2nd password to be submitted
        $this->assertEquals(200, $response->getStatus());
        $this->assertArrayHasKey('continuationToken', $jsondata);
        $this->assertArrayHasKey('prompt', $jsondata);

        // submit (wrong) 2nd password
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => '' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // expect auth failure response (401)
        $this->assertEquals(401, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertArrayHasKey('continuationToken', $jsondata);
        $this->assertArrayHasKey('prompt', $jsondata);
    }

    public function testAuthSuccess()
    {
        // initial auth step
        $data = [ 'username' => 'jane.doe', 'clientName' => 'PHPUnit/JmapAuthTest', 'clientVersion' => '0.0.1', 'deviceName' => 'CLI' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatus());

        // auth continue step
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => '123456' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // requires a 2nd password to be submitted
        $this->assertEquals(200, $response->getStatus());
        $this->assertArrayHasKey('continuationToken', $jsondata);
        $this->assertArrayHasKey('prompt', $jsondata);

        // submit 2nd password
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => 'xxxxxx' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // expect auth success
        $this->assertEquals(201, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertArrayHasKey('accessToken', $jsondata);
        $this->assertArrayHasKey('api', $jsondata);
    }

    public function testAuthProviderChain()
    {
        // initial auth step
        $data = [ 'username' => 'jane.doe', 'clientName' => 'PHPUnit/JmapAuthTest', 'clientVersion' => '0.0.1', 'deviceName' => 'CLI' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        $this->assertEquals(200, $response->getStatus());

        // auth continue step
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => '123456' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // requires a 2nd password to be submitted
        $this->assertEquals(200, $response->getStatus());
        $this->assertArrayHasKey('continuationToken', $jsondata);
        $this->assertArrayHasKey('prompt', $jsondata);

        // submit 1st password for 2nd factor
        $method = $jsondata['methods'][0];
        $data = [ 'token' => $jsondata['continuationToken'], 'method' => $method, 'password' => '123456' ];
        $response = $this->sendRequest($data, '/auth');
        $jsondata = json_decode($response->getBody(), true);

        // expect auth failure
        $this->assertEquals(401, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
    }

}