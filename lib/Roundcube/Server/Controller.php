<?php

/**
 * Roundcube Server HTTP request controller
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

namespace Roundcube\Server;

use Roundcube\JMAP,
    Roundcube\Logger,
    Roundcube\Utils,
    Sabre\Event,
    Sabre\HTTP,
    Sabre\HTTP\RequestInterface,
    Sabre\HTTP\ResponseInterface;

class Controller implements Event\EventEmitterInterface
{
    use Event\EventEmitterTrait;

    /**
     * httpResponse
     *
     * @var Sabre\HTTP\Response
     */
    public $httpResponse;

    /**
     * httpRequest
     *
     * @var Sabre\HTTP\Request
     */
    public $httpRequest;

    /**
     * PHP HTTP Sapi
     *
     * @var Sabre\HTTP\Sapi
     */
    public $sapi;

    /**
     * HTTP request routes and the registered handlers
     *
     * @var array
     */
    public $routes = [];

    /**
     * List of registered processor instances
     *
     * @var array
     */
    public $processors = [];

    /**
     * The Logger instance
     *
     * @var Roundcube\Logger
     */
    public $logger;

    /**
     * Instance of the authenticated user identity
     *
     * @var Roundcube\Server\Auth\AuthenticatedIdentity
     */
    public $identity;

    /**
     *
     */
    public function __construct()
    {
        $this->sapi = new HTTP\Sapi();
        $this->httpResponse = new HTTP\Response();
        $this->httpRequest = $this->sapi->getRequest();
        $this->logger = Logger::get('controller');

        // FIXME: JMAP Authorization headers don't get through to the Request object
        if (function_exists('apache_request_headers')) {
            $hdrs = apache_request_headers();
            if (isset($hdrs['Authorization']))
                $this->httpRequest->setHeader('Authorization', $hdrs['Authorization']);
        }

        // plugins can add more callbacks directly to $this->routes
    }

    /**
     *
     */
    public function addProcessor($processor)
    {
        $processor->init($this);
        $this->processors[] = $processor;
    }

    /**
     *
     */
    public function url($route, $full = false)
    {
        $url = $this->httpRequest->getBaseUrl() . $route;
        return $full ? Utils::fullyQualifiedUrl($url) : $url;
    }

    /**
     *
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     *
     */
    public function process()
    {
        $this->emit('process:before', [ ['request' => $this->httpRequest] ]);

        // set Content Security Policy and CORS headers
        $this->httpResponse->addHeader('Content-Security-Policy', "default-src *");
        $this->httpResponse->addHeader('X-Content-Security-Policy', "default-src *");

        if ($this->httpRequest->hasHeader('Origin')) {
            // TODO: allow to configure allowed origins
            $this->httpResponse->addHeader('Access-Control-Allow-Origin', "*");
        }

        // FIXME: respond to OPTIONS requests directly and without validation
        if ($this->httpRequest->getMethod() == 'OPTIONS') {
            $this->httpResponse->addHeader('Access-Control-Request-Method', 'GET, POST, OPTIONS');
            $this->httpResponse->addHeader('Access-Control-Allow-Headers', $this->httpRequest->getHeader('Access-Control-Request-Headers'));
            $this->httpResponse->setStatus(204);
            $this->sapi->sendResponse($this->httpResponse);
            return;
        }

        // extract route from request (jmap, auth|.well-known/jmap, upload)
        if ($route = $this->getRouteMatch($this->httpRequest->getPath())) {
            try {
                call_user_func($this->routes[$route], $this->httpRequest, $this->httpResponse);
            }
            catch (\RuntimeException $e) {
                if ($e instanceof Exception\ProcessorException)
                    $this->httpResponse->setStatus($e->getStatusCode());
                else
                    $this->httpResponse->setStatus(500);

                $this->logger->err(strval($e));
                $this->emit('process:error', [ ['request' => $this->httpRequest, 'exception' => $e ] ]);
            }
        }
        else {
            // TODO: throw invalid route error
            $this->httpResponse->setStatus(404);
        }

        $this->emit('process:after', [ ['response' => $this->httpResponse] ]);

        $this->sapi->sendResponse($this->httpResponse);
    }

    /**
     * Match the given URI path with known routes
     *
     * @param string $path The URI path to match
     * @return string The matching route name or false if none matches
     */
    protected function getRouteMatch($path)
    {
        // fast-track: check direct match
        if (!empty($this->routes[$path]))
            return $path;

        // try to match a RFC6570 URI template route
        foreach (array_keys($this->routes) as $route) {
            $expr = '!^' . preg_replace('/\{[a-z0-9:?-]+\}/Ui', '(.+)', $route) . '$!';
            if (preg_match($expr, $path)) {
                return $route;
            }
        }

        return false;
    }
}
