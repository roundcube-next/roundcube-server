<?php

/**
 * Roundcube Server Authentication request processor
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

use Roundcube\Server\App;
use Roundcube\Server\Controller;
use Roundcube\Server\Auth\ProviderInterface;
use Roundcube\Server\Auth\AuthenticatedIdentity;
use Roundcube\Server\Exception\ProcessorException;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;

/**
 * Authentication request processor
 */
class Auth implements ProcessorInterface
{
    protected $ctrl;

    protected $session;

    protected $providers = [];

    /**
     *
     */
    public function init(Controller $controller)
    {
        $this->ctrl = $controller;

        // register route /auth to controller
        $controller->routes['auth'] = [$this, 'process'];
        $controller->routes['.well-known/jmap'] = [$this, 'process'];

        $app = App::getInstance();
        $config = $app->get('Config');
        $this->session = $app->get('Session');

        // load providers from config
        foreach ((array)$config->get('auth.providers') as $providerclass) {
            try {
                $provider = $app->get($providerclass);
                if ($provider instanceof ProviderInterface) {
                    $this->providers[] = $provider;
                }
                else {
                    throw new \RuntimeException("The provider class $providerclass doesn't implement Roundcube\Server\Auth\ProviderInterface");
                }
            }
            catch (\RuntimeException $e) {
                // TODO: log this
            }
        }
    }

    /**
     * Getter for a map of known JMAP endpoints and their connected controller routes
     *
     * @return array Empty for this processor
     */
    public function getJmapRoutes()
    {
        return array();
    }

    /**
     * Handle request to JMAP authentication endpoint
     */
    public function process(Request $request, Response $response)
    {
        switch ($request->getMethod()) {
            case 'GET':
                $this->processGET($request, $response);
                break;

            case 'POST':
                $this->processPOST($request, $response);
                break;

            default:
                throw new ProcessorException(405, "Method " . $request->getMethod() . " not allowed");
        }
    }

    /**
     * Handle GET request for refetching URL endpoints
     */
    public function processGET(Request $request, Response $response)
    {
        $status = self::checkJmapAuth($request);

        if ($status === 200) {
            $response->setHeader('Content-Type', 'application/json');
            $this->sendAuthSuccess($response);
        }
        else {
            $response->setStatus($status);
        }
    }

    /**
     * Handle JMAP authentication requests (POST)
     */
    public function processPOST(Request $request, Response $response)
    {
        // decode JMAP request data
        $json_input = json_decode($request->getBodyAsString(), true);

        if ($json_input === null)
            throw new ProcessorException(400, "Invalid JSON request body");

        $response->setHeader('Content-Type', 'application/json');

        // initial auth request
        if (isset($json_input['username'])) {
            $this->ctrl->emit('jmap:auth:init', [ [ 'data' => $json_input ] ]);

            $methods = [];
            foreach ($this->providers as $provider) {
                $methods = array_merge($methods, $provider->getAuthMethods());
            }

            // start session and generate the continuationToken
            $this->session->start();
            $this->session->set('Auth\username', $json_input['username']);

            $result = [ 'methods' => array_unique($methods), 'continuationToken' => $this->session->key ];

            // add prompt phrase stored in config
            if ($prompt = App::getInstance()->get('Config')->get('auth.prompt'))
                $result['prompt'] = $prompt;

            $status = 200;
            $this->ctrl->emit('jmap:auth:more', [ ['result' => &$result, 'status' => &$status ] ]);

            $response->setBody(json_encode($result));
            $response->setStatus($status);
        }
        // auth continuation request
        else if (isset($json_input['token']) && isset($json_input['method'])) {
            $this->ctrl->emit('jmap:auth:continue', [ [ 'data' => $json_input ] ]);

            // validate token (which is the session key) ...
            $this->session->start($json_input['token']);
            if ($this->session->key !== $json_input['token'] || empty($this->session->get('Auth\username'))) {
                $this->ctrl->emit('jmap:auth:restart', [ [ 'input' => $json_input, 'status' => 403 ] ]);
                $response->setStatus(403);  // Restart authentication
                return;
            }

            // ...and get username from session
            $json_input['username'] = $this->session->get('Auth\username');
            $authenticated = false;
            foreach ($this->providers as $provider) {
                if (in_array($json_input['method'], $provider->getAuthMethods())) {
                    $authenticated = $provider->authenticate($json_input);
                }
                if ($authenticated) {
                    break;
                }
            }

            // authentication successful
            if ($authenticated && $authenticated instanceof AuthenticatedIdentity) {
                $authenticated->username = $json_input['username'];

                // replace session and generate the accessToken
                $this->session->regenerateId(true);
                $this->session->set('Auth\authenticated', time());
                $this->session->set('Auth\identity', $authenticated);

                // send success response
                $this->sendAuthSuccess($response, $this->session->key);
            }
            else {
                // report authentication failure
                // TODO: create the same response as in initial auth request above
                $this->ctrl->emit('jmap:auth:failure', [ [ 'input' => $json_input, 'status' => 401 ] ]);
                $response->setStatus(401);
            }
        }
        else {
            $response->setStatus(400);
        }
    }

    /**
     *
     */
    public static function checkJmapAuth(Request $request, AuthenticatedIdentity &$identity = null)
    {
        // check authentication status
        $token = $request->getHeader('Authorization');

        if (empty($token)) {
            return 401;
        }

        // load session data for the given auth token
        $session = App::getInstance()->get('Session');
        $session->start($token);

        if (empty($session->get('Auth\authenticated'))) {
            return 401;
        }

        // load identity from session
        $identity = $session->get('Auth\identity');
        return 200;
    }

    /**
     *
     */
    protected function sendAuthSuccess(Response $response, $accessToken = null)
    {
        // mandatory JMAP API endpoints
        $routes = [
            'api'         => '!undefined',
            'upload'      => '!undefined',
            'download'    => '!undefined',
            'eventSource' => '!undefined',
        ];

        // collect service endpoint routes for the registered processors
        foreach ($this->ctrl->processors as $processor) {
            $routes = array_merge($routes, $processor->getJmapRoutes());
        }

        // send service endpoint URLs
        $result = [];
        foreach ($routes as $key => $route) {
            $result[$key] = $this->ctrl->url($route, true);
        }

        if (!empty($accessToken))
            $result['accessToken'] = $accessToken;

        $status = $accessToken ? 201 : 200;
        $this->ctrl->emit('jmap:auth:success', [ [ 'result' => &$result, 'status' => &$status ] ]);

        $response->setBody(json_encode($result));
        $response->setStatus($status);
    }
}