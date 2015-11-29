<?php

/**
 * Roundcube Server JMAP Mail provider interface
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
 * Interface defining a JMAP Mail provider
 */
interface MailProviderInterface
{
    /* mandatory methods for mail providers */

    public function getMailboxes($args);

    public function getMailboxUpdates($args);

    public function setMailboxes($args);

    public function getMessageList($args);

    public function getMessageListUpdates($args);

    public function getThreads($args);

    public function getThreadUpdates($args);

    public function getMessages($args);

    public function getMessageUpdates($args);

    public function setMessages($args);

    public function importMessage($args);

    public function copyMessages($args);

    public function reportMessages($args);
}
