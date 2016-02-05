<?php

/**
 * Roundcube Server plugin to translate legacy account capabilities
 * into the most recent JMAP spec
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

use Roundcube\Server\Plugin\AbstractPlugin;

class LegacyAccountCapabilities extends AbstractPlugin
{
    /**
     * Initialize plugin
     *
     * Register listeners on controller events
     */
    public function init()
    {
        $controller = $this->app->get('Controller');
        $controller->on('jmap:response', [$this, 'jmapResponse']);
    }

    /**
     * Handler for jmap:response events
     */
    public function jmapResponse($args)
    {
        if ($args['method'] === 'getAccounts' && !empty($args['result']) && $args['result'][0] == 'accounts' && isset($args['result'][1]['list'])) {
            foreach ($args['result'][1]['list'] as $i => $account) {
                $account = &$args['result'][1]['list'][$i];
                $isReadonly = !empty($account['isReadOnly']);

                if (!empty($account['hasMail']) && !isset($account['mail'])) {
                    $account['mail'] = ['isReadOnly' => $isReadonly, 'canDelaySend' => false];
                }
                if (!empty($account['hasContacts']) && !isset($account['contacts'])) {
                    $account['contacts'] = ['isReadOnly' => $isReadonly];
                }
                if (!empty($account['hasCalendars']) && !isset($account['calendars'])) {
                    $account['calendars'] = ['isReadOnly' => $isReadonly];
                }
            }
        }
    }
}