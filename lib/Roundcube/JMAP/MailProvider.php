<?php

/**
 * Roundcube Server JMAP Mail provider base class
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


namespace Roundcube\JMAP;

/**
 * Absract class representing a JMAP Mail provider
 */
abstract class MailProvider extends Provider implements MailProviderInterface
{
    /**
     * Getter for the list of services this provider supports
     *
     * For Mail providers this is simply 'Mail'
     *
     * @return array
     */
    public function getServices()
    {
        return [ 'Mail' ];
    }

    /**
     * Getter for the list of JMAP methods this provider supports
     *
     * @return array
     */
    public function getMethods()
    {
        return [
            'getMailboxes',
            'getMailboxUpdates',
            'setMailboxes',
            'getMessageList',
            'getMessageListUpdates',
            'getThreads',
            'getThreadUpdates',
            'getMessages',
            'getMessageUpdates',
            'setMessages',
            'importMessage',
            'copyMessages',
            'reportMessages',
        ];
    }

}
