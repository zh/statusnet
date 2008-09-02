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

define('CLAIM_TIMEOUT', 1200);

class XmppConfirmHandler {

	var $_id = 'confirm';
	
	function XmppConfirmHandler($id=NULL) {
		if ($id) {
			$this->_id = $id;
		}
	}

	function start() {
		# Low priority; we don't want to receive messages
		$this->log(LOG_INFO, "INITIALIZE");
		$this->conn = jabber_connect($this->_id);
		if ($this->conn) {
			$this->conn->addEventHandler('message', 'forward_message', $this);
			$this->conn->addEventHandler('reconnect', 'handle_reconnect', $this);
			$this->conn->reconnectTimeout(600);
			jabber_send_presence("Send me a message to post an notice", 'available', NULL, 'available', -1);
		}
		return !is_null($this->conn);
	}
	
	function handle_reconnect(&$pl) {
		$this->conn->processUntil('session_start');
		$this->conn->presence(NULL, 'available', NULL, 'available', -1);
	}
	
	function handle_queue() {
		$this->log(LOG_INFO, 'checking for queued confirmations');
		do {
			$confirm = $this->next_confirm();
			if ($confirm) {
				$this->log(LOG_INFO, 'Sending confirmation for ' . $confirm->address);
				$user = User::staticGet($confirm->user_id);
				if (!$user) {
					$this->log(LOG_WARNING, 'Confirmation for unknown user ' . $confirm->user_id);
					continue;
				}
				$success = jabber_confirm_address($confirm->code,
												  $user->nickname,
												  $confirm->address);
				if (!$success) {
					$this->log(LOG_ERR, 'Confirmation failed for ' . $confirm->address);
					# Just let the claim age out; hopefully things work then
					continue;
				} else {
					$this->log(LOG_INFO, 'Confirmation sent for ' . $confirm->address);
					# Mark confirmation sent
					$original = clone($confirm);
					$confirm->sent = $confirm->claimed;
					$result = $confirm->update($original);
					if (!$result) {
						$this->log(LOG_ERR, 'Cannot mark sent for ' . $confirm->address);
						# Just let the claim age out; hopefully things work then
						continue;
					}
				}
				$this->idle(0);
			} else {
#				$this->clear_old_confirm_claims();
				$this->idle(10);
			}
		} while (true);
	}

	function next_confirm() {
		$confirm = new Confirm_address();
		$confirm->whereAdd('claimed IS NULL');
		$confirm->whereAdd('sent IS NULL');
		# XXX: eventually we could do other confirmations in the queue, too
		$confirm->address_type = 'jabber';
		$confirm->orderBy('modified DESC');
		$confirm->limit(1);
		if ($confirm->find(TRUE)) {
			$this->log(LOG_INFO, 'Claiming confirmation for ' . $confirm->address);
		        # working around some weird DB_DataObject behaviour
			$confirm->whereAdd(''); # clears where stuff
			$original = clone($confirm);
			$confirm->claimed = common_sql_now();
			$result = $confirm->update($original);
			if ($result) {
				$this->log(LOG_INFO, 'Succeeded in claim! '. $result);
				return $confirm;
			} else {
				$this->log(LOG_INFO, 'Failed in claim!');
				return false;
			}
		}
		return NULL;
	}

	function clear_old_confirm_claims() {
		$confirm = new Confirm();
		$confirm->claimed = NULL;
		$confirm->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
		$confirm->update(DB_DATAOBJECT_WHEREADD_ONLY);
	}
	
	function log($level, $msg) {
		common_log($level, 'XmppConfirmHandler ('. $this->_id .'): '.$msg);
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

$resource = ($argc > 1) ? $argv[1] : (common_config('xmpp', 'resource').'-confirm');

$handler = new XmppConfirmHandler($resource);

if ($handler->start()) {
	$handler->handle_queue();
}

$handler->finish();
