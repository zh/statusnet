<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/queuehandler.php');

/**
 * Common superclass for all XMPP-using queue handlers. They all need to
 * service their message queues on idle, and forward any incoming messages
 * to the XMPP listener connection. So, we abstract out common code to a
 * superclass.
 */

class XmppQueueHandler extends QueueHandler
{
    function start()
    {
        # Low priority; we don't want to receive messages
        $this->log(LOG_INFO, "INITIALIZE");
        $this->conn = jabber_connect($this->_id.$this->transport());
        if ($this->conn) {
            $this->conn->addEventHandler('message', 'forward_message', $this);
            $this->conn->addEventHandler('reconnect', 'handle_reconnect', $this);
            $this->conn->setReconnectTimeout(600);
            jabber_send_presence("Send me a message to post a notice", 'available', null, 'available', -1);
        }
        return !is_null($this->conn);
    }

    function handle_reconnect(&$pl)
    {
        $this->conn->processUntil('session_start');
        $this->conn->presence(null, 'available', null, 'available', -1);
    }

    function idle($timeout=0)
    {
        # Process the queue for as long as needed
        try {
            if ($this->conn) {
                $this->conn->processTime($timeout);
            }
        } catch (XMPPHP_Exception $e) {
            $this->log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
            die($e->getMessage());
        }
    }

    function forward_message(&$pl)
    {
        if ($pl['type'] != 'chat') {
            $this->log(LOG_DEBUG, 'Ignoring message of type ' . $pl['type'] . ' from ' . $pl['from']);
            return;
        }
        $listener = $this->listener();
        if (strtolower($listener) == strtolower($pl['from'])) {
            $this->log(LOG_WARNING, 'Ignoring loop message.');
            return;
        }
        $this->log(LOG_INFO, 'Forwarding message from ' . $pl['from'] . ' to ' . $listener);
        $this->conn->message($this->listener(), $pl['body'], 'chat', null, $this->ofrom($pl['from']));
    }

    function ofrom($from)
    {
        $address = "<addresses xmlns='http://jabber.org/protocol/address'>\n";
        $address .= "<address type='ofrom' jid='$from' />\n";
        $address .= "</addresses>\n";
        return $address;
    }

    function listener()
    {
        if (common_config('xmpp', 'listener')) {
            return common_config('xmpp', 'listener');
        } else {
            return jabber_daemon_address() . '/' . common_config('xmpp','resource') . '-listener';
        }
    }
}
