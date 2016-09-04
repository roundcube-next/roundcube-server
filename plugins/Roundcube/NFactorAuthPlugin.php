<?php

/**
 * Sample plugin demonstrating a 2-factor authentication process through JMAP
 *
 * @author Thomas Bruederli <thomas@roundcube.net>
 *
 * Copyright (C) 2016, Roundcube Dev Team
 *
 * Licensed under the GNU General Public License version 3 or
 * any later version as published by the Free Software Foundation.
 * For full license information see http://www.gnu.org/licenses/gpl-3.0
 */

namespace Roundcube\Plugins;

use Roundcube\Logger;
use Roundcube\Server\Auth\AuthMethod;
use Roundcube\Server\Plugin\AbstractPlugin;
use Roundcube\Server\Auth\ProviderInterface;
use Roundcube\Server\Auth\AuthenticatedIdentity;
use Roundcube\Server\Exception\AuthenticationAbortedException;

class NFactorAuthPlugin extends AbstractPlugin implements ProviderInterface
{
    protected static $skey = 'Roundcube\Plugins\NFactorAuthPlugin\require-2fa';

    protected $authSuccess = false;

    /**
     * Initialize plugin
     *
     * Register listeners on controller events
     */
    public function init()
    {
        $controller = $this->app->get('Controller');
        $controller->on('jmap:auth:continue', [$this, 'jmapAuthContinue']);
        $controller->on('jmap:auth:success', [$this, 'jmapAuthSuccess']);
    }

    public function jmapAuthContinue($args)
    {
        $this->authSuccess = false;
        $session = $this->app->get('Session');
        Logger::get('2fa')->debug('jmapAuthContinue', ['req' => $session->get(self::$skey)]);

        if ($session->get(self::$skey)) {
            // inject myself as an auth provider
            $args['processor']->addProvider($this, true);
        }
    }

    public function jmapAuthSuccess($args)
    {
        $session = $this->app->get('Session');
        $first = !$session->get(self::$skey);
        Logger::get('2fa')->debug('jmapAuthSuccess', ['first' => $first]);

        if ($first) {
            $session->set(self::$skey, time());
            $identity = $session->get('Auth\identity');
            if ($identity && $identity->username)
                $session->set('Auth\username', $identity->username);

            // respond with 200 and request 2nd factor password
            $args['status'] = 200;
            $args['result'] = [
                'methods' => $this->getAuthMethods(),
                'loginId' => $session->key,
                'prompt' => 'TOTP authentication required (6 times x)',
            ];
        }
        else if ($this->authSuccess) {
            $session->remove(self::$skey);
        }
    }

    /**
     * List authentication methods this provier supports
     *
     * @return array
     */
    public function getAuthMethods()
    {
        return [new AuthMethod('totp')];
    }

    /**
     * Do authenticate the given request data (password)
     *
     * @param array Data submitted with the authentication request
     * @return mixed Roundcube\Server\Auth\AuthenticatedIdentity on success, false on failure
     */
    public function authenticate(array $data)
    {
        $session = $this->app->get('Session');
        Logger::get('2fa')->debug('authenticate', ['data' => $data, 'identity' => $session->get('Auth\identity')]);

        // return the AuthenticatedIdentity already stored in session
        if (!empty($data['value']) && $data['value'] === $this->options['code'] && $session->get('Auth\identity')) {
            $this->authSuccess = true;
            return $session->get('Auth\identity');
        }
        else {
            throw new AuthenticationAbortedException("TOTP code doesn't match");
        }

        return false;
    }

}

