#!/usr/bin/env php
<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
	print "This script must be run from the command line\n";
	exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/jabber.php');
require_once(INSTALLDIR . '/lib/queuehandler.php');

set_error_handler('common_error_handler');

class PublicQueueHandler extends QueueHandler {
	
	function transport() {
		return 'public';
	}
	
	function start() {
		$this->log(LOG_INFO, "INITIALIZE");
		# Low priority; we don't want to receive messages

		$this->conn = jabber_connect($this->_id);
		if ($this->conn) {
			$this->conn->addEventHandler('message', 'forward_message', $this);
			$this->conn->addEventHandler('reconnect', 'handle_reconnect', $this);
			$this->conn->setReconnectTimeout(600);
			jabber_send_presence("Send me a message to post a notice", 'available', NULL, 'available', -1);
		}
		return !is_null($this->conn);
	}

	function handle_reconnect(&$pl) {
		$this->conn->processUntil('session_start');
		$this->conn->presence(NULL, 'available', NULL, 'available', -1);
	}

	function handle_notice($notice) {
		return jabber_public_notice($notice);
	}
	
	function idle($timeout=0) {
		$this->conn->processTime($timeout);
	}

	function forward_message(&$pl) {
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
		$this->conn->message($this->listener(), $pl['body'], 'chat', NULL, $this->ofrom($pl['from']));
	}

	function ofrom($from) {
		$address = "<addresses xmlns='http://jabber.org/protocol/address'>\n";
		$address .= "<address type='ofrom' jid='$from' />\n";
		$address .= "</addresses>\n";
		return $address;
	}

	function listener() {
		if (common_config('xmpp', 'listener')) {
			return common_config('xmpp', 'listener');
		} else {
			return jabber_daemon_address() . '/' . common_config('xmpp','resource') . '-listener';
		}
	}
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

$resource = ($argc > 1) ? $argv[1] : (common_config('xmpp','resource') . '-public');

$handler = new PublicQueueHandler($resource);

if ($handler->start()) {
	$handler->handle_queue();
}

$handler->finish();
