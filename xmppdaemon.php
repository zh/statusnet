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

define('INSTALLDIR', dirname(__FILE__));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once('xmpp.php');

class XMPPDaemon {
	
	function XMPPDaemon() {
		foreach (array('server', 'port', 'user', 'password', 'resource') as $attr) {
			$this->$attr = common_config('xmpp', $attr);
		}
	}

	function connect() {
		$this->conn = new XMPP($this->host, $this->port, $this->user,
							   $this->password, $this->resource);
		if (!$this->conn) {
			return false;
		}
		$this->conn->connect();
		return !$this->conn->disconnected;
	}
	
	function handle() {
		while(!$this->conn->disconnected) {
			$payloads = $this->conn->processUntil(array('message', 'presence', 
														'end_stream', 'session_start'));
			foreach($payloads as $event) {
				$pl = $event[1];
				switch($event[0]) {
				 case 'message':
					$this->handle_message($pl);
					break;
				 case 'presence':
					$this->handle_presence($pl);
					break;
				 case 'session_start':
					$this->handle_session($pl);
					break;
				}
			}
		}
	}

	function handle_message(&$pl) {
		$user = User::staticGet('jabber', $pl['from']);
		if (!$user) {
			$this->log(LOG_WARNING, 'Message from unknown user ' . $pl['from']);
			return;
		}
		if ($this->handle_command($user, $pl['body'])) {
			return;
		} else {
			$this->add_notice($user, $pl);
		}
	}

	function handle_command($user, $body) {
		# XXX: localise
		switch(trim($body)) {
		 case 'on':
			$this->set_notify($user, true);
			return true;
		 case 'off':
			$this->set_notify($user, false);
			return true;
		 default:
			return false;
		}
	}
	
	function add_notice(&$user, &$pl) {
		$notice = new Notice();
		$notice->profile_id = $user->id;
		$notice->content = trim(substr($pl['body'], 0, 140));
		$notice->created = DB_DataObject_Cast::dateTime();
		$notice->query('BEGIN');
		$id = $notice->insert();
		if (!$id) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			$this->log(LOG_ERROR, 
					   'Could not insert ' . common_log_objstring($notice) . 
					   ' for user ' . common_log_objstring($user) . 
					   ': ' . $last_error->message);
			return;
		}
		$orig = clone($notice);
		$notice->uri = common_notice_uri($notice);
		$result = $notice->update($orig);
		if (!$result) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			$this->log(LOG_ERROR, 
					   'Could not add URI to ' . common_log_objstring($notice) . 
					   ' for user ' . common_log_objstring($user) . 
					   ': ' . $last_error->message);
			return;
		}
		common_broadcast_notice($notice);
	}
	
	function handle_presence(&$pl) {
		$user = User::staticGet('jabber', $pl['from']);
		if (!$user) {
			$this->log(LOG_WARNING, 'Message from unknown user ' . $pl['from']);
			return;
		}
		if ($user->updatefrompresence) {
			$this->add_notice($user, $pl);
		}
	}
	
	function handle_session(&$pl) {
		$conn->presence($status="Send me a message to post a notice");
	}
	
	function log($level, $msg) {
		common_log($level, 'XMPPDaemon('.$this->resource.'): '.$msg);
	}
}


?>
