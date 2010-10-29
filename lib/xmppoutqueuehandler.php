<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Queue handler for pre-processed outgoing XMPP messages.
 * Formatted XML stanzas will have been pushed into the queue
 * via the Queued_XMPP connection proxy, probably from some
 * other queue processor.
 *
 * Here, the XML stanzas are simply pulled out of the queue and
 * pushed out over the wire; an XmppManager is needed to set up
 * and maintain the actual server connection.
 *
 * This queue will be run via XmppDaemon rather than QueueDaemon.
 *
 * @author Brion Vibber <brion@status.net>
 */
class XmppOutQueueHandler extends QueueHandler
{
    function transport() {
        return 'xmppout';
    }

    /**
     * Take a previously-queued XMPP stanza and send it out ot the server.
     * @param string $msg
     * @return boolean true on success
     */
    function handle($msg)
    {
        assert(is_string($msg));

        $xmpp = XmppManager::get();
        $ok = $xmpp->send($msg);

        return $ok;
    }
}
