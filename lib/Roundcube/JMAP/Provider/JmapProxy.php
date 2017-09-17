<?php

/**
 * Perl JMAP Proxy Server provider class
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
use Sabre\HTTP\Request;
use Roundcube\Logger;
use Roundcube\Server\App;
use Roundcube\Server\Controller;
use Roundcube\Server\Processor\ProcessorInterface;
use Roundcube\Server\Auth\AuthMethod;
use Roundcube\JMAP\MailProvider;
use Roundcube\JMAP\Exception\RuntimeException;
use Roundcube\Server\Auth\AuthenticatedIdentity;
use Roundcube\Server\Auth\ProviderInterface as AuthProviderInterface;

/**
 * MailProvider class connecting to a Perl JMAP Proxy Server
 * from https://github.com/jmapio/jmap-perl
 */
class JmapProxy extends MailProvider implements AuthProviderInterface
{
    protected $proxyconfig;

    protected $logger;

    protected $reqid = 1;

    protected $proxyuri;

    /**
     *
     */
    public function __construct()
    {
        $app = App::getInstance();
        $config = $app->get('Config');
        $this->proxyconfig = $config->get('jmapproxy');
        $this->logger = Logger::get('jmapproxy');
    }

    /**
     * List authentication methods this provier supports
     *
     * @return array
     */
    public function getAuthMethods()
    {
        return [new AuthMethod('password')];
    }

    /**
     * Do authenticate the given request data (username, password, etc.)
     *
     * @return mixed Roundcube\Server\Auth\AuthenticatedIdentity on success, false on failure
     */
    public function authenticate(array $data)
    {
        $success = false;

        if (empty($data['username']) || empty($data['value'])) {
            // TODO: throw exception? log error?
            return $success;
        }

        // POST to jmapproxy.url /signup
        $request = new HTTP\Request('POST', $this->proxyconfig['url'] . '/signup');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $postdata = $this->proxyconfig['signup'] + ['username' => $data['username'], 'password' => $data['value']];
        $request->setBody(http_build_query($postdata));

        // $this->logger->debug('authenticate:request', ['dump' => strval($request)]);

        try {
            $client = new HttpClient();
            $this->setCurlProxySettings($client);
            $response = $client->send($request);
            $this->logger->debug('authenticate:response', ['dump' => strval($response)]);

            // on success, call getAccounts and return primary account as identity
            if (in_array((int)$response->getStatus(), [301, 302, 307, 308]) && ($location = $response->getHeader('Location'))) {
                // fetch account information
                $this->proxyuri = $this->proxyconfig['url'] . parse_url($location, PHP_URL_PATH);

                foreach ($this->getAccounts() as $account) {
                    // return AuthenticatedIdentity object
                    $success = new AuthenticatedIdentity([
                        'id' => $account['id'],
                        'name' => $account['name'],
                        'uri' => $this->proxyuri,
                    ]);

                    if (!empty($account['isPrimary']))
                        break;
                }
            }
        }
        catch (HTTP\ClientException $e) {
            $this->logger->err('JmapProxy HTTP Error: ' . $e->getMessage());
            $success = false;
        }
        catch (HTTP\ClientHttpException $e) {
            $this->logger->err('JmapProxy HTTP Error: ' . $e->getMessage(),
                ['status' => $e->getHttpStatus(), 'response' => $e->getResponse()]);
            $success = false;
        }

        return $success;
    }

    /**
     * Getter for the (primary) account ID this provider belongs to
     *
     * @return string
     */
    public function getAccountID()
    {
        $identity = $this->controller->getIdentity();
        return $identity ? $identity->id : null;
    }

    /**
     * Getter for the list of accounts this provider is connected with
     *
     * @return array
     */
    public function getAccounts()
    {
        $accounts = [];
        foreach ($this->proxy('getAccounts') as $result) {
            if ($result[0] == 'accounts' && isset($result[1]['list'])) {
                $accounts = $result[1]['list'];
            }
            else if ($result[0] == 'error') {
                // throw exception for JMAP error
                throw new RuntimeException($result[1]['message']);
            }
        }
        return $accounts;
    }

    /**
     * Proxy the given JMAP message to the connected JMAP server
     *
     * @param string $method The name of the method to be called on the server
     * @param array  $args   Object containing named arguments for that method or response
     * @return array List of response messages returned by the server
     */
    protected function proxy($method, array $args = [])
    {
        if (!isset($this->proxyuri) && ($identity = $this->controller->getIdentity()))
            $this->proxyuri = $identity->uri;

        if (empty($this->proxyuri))
            throw new RuntimeException("JmapProxy Error: unauthenticated; missing session URI");

        $error = 'Not Implemented';
        $tag = '#' . $this->reqid++;

        $request = new HTTP\Request('POST', $this->proxyuri);
        $request->setHeader('Content-Type', 'application/json');
        $request->setBody(json_encode([[$method, (object)$args, $tag]]));

        $this->logger->debug('proxy:request ' . $method, ['args' => $args, 'uri' => $this->proxyuri, 'tag' => $tag]);

        try {
            $client = new HttpClient();
            $this->setCurlProxySettings($client);
            $response = $client->send($request);
            $this->logger->debug('proxy:response', ['dump' => strval($response)]);

            if ($response->getStatus() === 200) {
                $results = json_decode($response->getBodyAsString(), true);
                return array_filter($results, function($res) use ($tag) { return count($res) == 3 && $res[2] == $tag; });
            }

            $error = 'Unexpected HTTP response: ' . $response->getStatus() . ' ' . $response->getStatusText();
        }
        catch (HTTP\ClientHttpException $e) {
            $error = 'JmapProxy HTTP Error: ' . $e->getHttpStatus() . ' ' . $e->getMessage();
        }
        catch (\Exception $e) {
            $error = 'JmapProxy HTTP Error: ' . $e->getMessage();
        }

        $this->logger->err($error);

        // fail with a runtime error
        throw new RuntimeException($error);
    }

    /**
     * Apply cURL options from proxy confit go the given Http client instance
     */
    protected function setCurlProxySettings($client)
    {
        $optionsmap = [
            'ssl_verifypeer' => CURLOPT_SSL_VERIFYPEER,
            'ssl_verifyhost' => CURLOPT_SSL_VERIFYHOST,
        ];

        foreach ($this->proxyconfig as $key => $value) {
            if (isset($optionsmap[$key])) {
                $client->addCurlSetting($optionsmap[$key], $value);
            }
        }
    }

    public function getMailboxes($args)
    {
        return $this->proxy('getMailboxes', $args);
    }

    public function getMailboxUpdates($args)
    {
        return $this->proxy('getMailboxes', $args);
    }

    public function setMailboxes($args)
    {
        return $this->proxy('setMailboxes', $args);
    }

    public function getMessageList($args)
    {
        return $this->proxy('getMessageList', $args);
    }

    public function getMessageListUpdates($args)
    {
        return $this->proxy('getMessageListUpdates', $args);
    }

    public function getThreads($args)
    {
        return $this->proxy('getThreads', $args);
    }

    public function getThreadUpdates($args)
    {
        return $this->proxy('getThreadUpdates', $args);
    }

    public function getMessages($args)
    {
        return $this->proxy('getMessages', $args);
    }

    public function getMessageUpdates($args)
    {
        return $this->proxy('getMessageUpdates', $args);
    }

    public function setMessages($args)
    {
        return $this->proxy('setMessages', $args);
    }

    public function importMessage($args)
    {
        return $this->proxy('importMessage', $args);
    }

    public function copyMessages($args)
    {
        return $this->proxy('copyMessages', $args);
    }

    public function reportMessages($args)
    {
        return $this->proxy('reportMessages', $args);
    }

}
