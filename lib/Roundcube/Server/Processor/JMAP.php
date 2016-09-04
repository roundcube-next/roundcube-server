<?php

/**
 * Roundcube Server JMAP request processor
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
use Roundcube\Server\Exception\ProcessorException;
use Roundcube\JMAP\Provider as JmapProvider;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Sabre\HTTP\Auth as HTTPAuth;

/**
 * JMAP request processor
 */
class JMAP implements ProcessorInterface
{
    protected $ctrl;

    protected $providers = [];

    protected $methodmap = [];

    /**
     *
     */
    public function init(Controller $controller)
    {
        $this->ctrl = $controller;

        // register route /jmap to controller
        $controller->routes['jmap'] = [$this, 'process'];

        // register myself as provider for some JMAP commands
        $this->methodmap['getAccounts'] = [ $this ];

        $app = App::getInstance();
        $config = $app->get('Config');

        // load providers from config
        foreach ((array)$config->get('jmap.providers') as $providerclass) {
            try {
                $provider = $app->get($providerclass);
                if ($provider instanceof JmapProvider) {
                    $this->addProvider($provider);
                }
                else {
                    throw new \RuntimeException("The provider class $providerclass doesn't implement Roundcube\JMAP\Provider");
                }
            }
            catch (\RuntimeException $e) {
                // TODO: log this
            }
        }

        // allow plugins to register JMAP providers
        $controller->emit('jmap:init', [ $this ]);
    }

    /**
     * Getter for a map of known JMAP endpoints and their connected controller routes
     *
     * @return array Empty for this processor
     */
    public function getJmapRoutes()
    {
        return [
            'apiUrl'      => 'jmap',
            'downloadUrl' => 'download/{blobId}/{name}',
        ];
    }

    /**
     * Handle JMAP client requests
     */
    public function process(Request $request, Response $response)
    {
        switch ($request->getMethod()) {
            case 'POST':
                $this->processPOST($request, $response);
                break;

            default:
                throw new ProcessorException(405, "Method " . $request->getMethod() . " not allowed");
        }

        if ($response->getStatus() === 401) {
            $response->setHeader('WWW-Authenticate', Auth::SCHEME);
        }
    }

    /**
     *
     * @throws ProcessorException
     */
    public function processPOST(Request $request, Response $response)
    {
        // check authentication status
        $auth_status = Auth::checkJmapAuth($request, $identity);

        if ($auth_status !== 200) {
            return $response->setStatus($auth_status);
        }

        // set authenticated identity to controller
        $this->ctrl->identity = $identity;

        // replace response object with a JMAP response
        $response = new \Roundcube\JMAP\Response($response->getStatus(), $response->getHeaders());
        $this->ctrl->httpResponse = $response;

        // decode JMAP request data
        $json_input = json_decode($request->getBodyAsString(), true);

        if ($json_input === null)
            throw new ProcessorException(400, "Invalid JSON request body");

        $this->ctrl->emit('jmap:query', [['query' => $json_input, 'auth' => &$this->ctrl->identity ]]);

        // dispatch each query command to the registered providers
        foreach ($json_input as $cmd) {
            list($method, $args, $id) = $cmd;

            if (isset($this->methodmap[$method])) {
                foreach ($this->invokeProviders($method, $args) as $res) {
                    $this->ctrl->emit('jmap:response', [['method' => $method, 'args' => $args, 'result' => &$res]]);
                    $this->ctrl->emit('jmap:response:' . $method, [['args' => $args, 'result' => &$res]]);
                    $response->addResponse($res[0], $res[1], $id);
                }
            }
            else {
                $res = ['error', ['type' => 'unknownMethod']];
                $this->ctrl->emit('jmap:error', [['method' => $method, 'args' => $args, 'result' => &$res]]);
                $response->addResponse($res[0], $res[1], $id);
            }
        }

        $response->setStatus(200);
    }

    /**
     * Helper method to invoke all registered provider callbacks for a given command
     *
     */
    protected function invokeProviders($method, $args)
    {
        $results = [];
        foreach ($this->methodmap[$method] as $handler) {
            try {
                $res = call_user_func([$handler, $method], $args, $results);
            }
            catch (\RuntimeException $e) {
                // TODO: handle exceptions (known as per JMAP spec and unknown)
                $res = ['error', [ 'type' => 'runtimeError', 'details' => $e->getMessage() ]];
            }

            if (is_array($res[0])) {
                $results = array_merge($results, $res);
            }
            else {
                $results[] = $res;
            }
        }

        return $results;
    }

    /**
     * Register a JMAP provider instance to the processor
     *
     */
    public function addProvider(JmapProvider $provider)
    {
        $provider->init($this, $this->ctrl);
        $this->providers[] = $provider;

        foreach ($provider->getMethods() as $cmd) {
            if (!isset($this->methodmap[$cmd])) {
                $this->methodmap[$cmd] = [];
            }
            $this->methodmap[$cmd][] = $provider;
        }
    }

    /**
     * Handler for 'getAccounts' message
     *
     * see http://jmap.io/spec.html#getaccounts
     */
    public function getAccounts($args = [])
    {
        $accounts = [];

        foreach ($this->providers as $provider) {
            // each provider can serve several accounts
            foreach ($provider->getAccounts() as $paccount) {
                $accountID = $paccount['id'];

                if (empty($accountID)) {
                    $this->ctrl->logger->warn(get_class($provider) . " doesn't provide valid account information");
                    continue;
                }

                // provider refers to an already registered account
                if (isset($accounts[$accountID])) {
                    $account = $accounts[$accountID] + $paccount;
                }
                else {
                    // register a new account object with default properties
                    $account = $paccount + [
                        'id' => $accountID,
                        'hasMail' => false,
                        'hasContacts' => false,
                        'hasCalendars' => false,
                    ];
                }

                // register each provided service with a 'has<Service>' property
                foreach ($provider->getServices() as $service) {
                    $prop = 'has' . ucfirst($service);
                    $account[$prop] = true;
                }

                $accounts[$accountID] = $account;
            }
        }

        // TODO: determine 'state'
        return ['accounts', [ 'state' => '0000', 'list' => array_values($accounts) ]];
    }
}