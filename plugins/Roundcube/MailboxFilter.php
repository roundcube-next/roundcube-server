<?php

/**
 * Roundcube Server plugin to filter mailbox listing
 *
 * Hooks into JMAP getMailboxes requests and post-filter
 * the returned mailbox list.
 *
 * Config options:
 * 
 * - service: Roundcube\Server\Plugin\MailboxFilter
 *   options:
 *       # white-list allowed mailbox names
 *       allow:
 *           - Inbox
 *           - Drafts
 *           - Sent
 *           - Trash
 *           - Archive
 *       # black-list mailbox names
 *       disallow:
 *           - Configuration
 *           - Notes
 *       # white-list mailbox roles
 *       roles:
 *           - inbox
 *           - drafts
 *           - sent
 *           - trash
 *           - spam
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


class MailboxFilter extends AbstractPlugin
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

        if (!isset($this->options['allow']))
            $this->options['allow'] = [];
        if (!isset($this->options['disallow']))
            $this->options['disallow'] = [];
    }

    /**
     * Handler for jmap:response events
     */
    public function jmapResponse($args)
    {
        // filter mailbox list according to plugin options
        if ($args['method'] === 'getMailboxes' && !empty($args['result']) && $args['result'][0] == 'mailboxes' && isset($args['result'][1]['list'])) {
            $filtered = array_filter($args['result'][1]['list'], function($mbox) {
                $allow = !in_array($mbox['name'], $this->options['disallow']);
                if ($allow && !empty($this->options['roles']))
                    $allow = in_array($mbox['role'], $this->options['roles']);
                if ($allow && !empty($this->options['allow']))
                    $allow = in_array($mbox['name'], $this->options['allow']);
                return $allow;
            });

            $args['result'][1]['list'] = array_values($filtered);
        }
    }
}
