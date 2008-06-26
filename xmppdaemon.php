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

define('INSTALLDIR', dirname(__FILE__));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/jabber.php');

class XMPPDaemon {

	function XMPPDaemon($resource=NULL) {
		static $attrs = array('server', 'port', 'user', 'password',
					   'resource', 'host');

		foreach ($attrs as $attr)
		{
			$this->$attr = common_config('xmpp', $attr);
		}

		if ($resource) {
			$this->resource = $resource;
		}
	}

	function connect() {
		$this->conn = jabber_connect($this->resource,
								     "Send me a message to post a notice");
		if (!$this->conn) {
			return false;
		}
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
		if ($pl['type'] != 'chat') {
			return;
		}
		if (strlen($pl['body']) == 0) {
			return;
		}
		$from = jabber_normalize_jid($pl['from']);
		$user = User::staticGet('jabber', $from);
		if (!$user) {
			$this->log(LOG_WARNING, 'Message from unknown user ' . $from);
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

	function set_notify(&$user, $notify) {
		$orig = clone($user);
		$user->jabbernotify = $notify;
		$result = $user->update($orig);
		if (!$id) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			$this->log(LOG_ERROR,
					   'Could not set notify flag to ' . $notify .
					   ' for user ' . common_log_objstring($user) .
					   ': ' . $last_error->message);
		} else {
			$this->log(LOG_INFO,
					   'User ' . $user->nickname . ' set notify flag to ' . $notify);
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
		$notice->query('COMMIT');
		common_broadcast_notice($notice);
		$this->log(LOG_INFO,
				   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
	}

	function handle_presence(&$pl) {
		$from = jabber_normalize_jid($pl['from']);
		switch ($pl['type']) {
			case 'subscribe':
			    # We let anyone subscribe
				$this->subscribed($from);
				break;
			case 'subscribed':
			case 'unsubscribe':
			case 'unsubscribed':
				# XXX: do we care?
				break;
			default:
				if (!$pl['type']) {
					$user = User::staticGet('jabber', $from);
					if (!$user) {
						$this->log(LOG_WARNING, 'Message from unknown user ' . $from);
						return;
					}
					if ($user->updatefrompresence) {
						$this->add_notice($user, $pl);
					}
				}
				break;
		}
	}

	function log($level, $msg) {
		common_log($level, 'XMPPDaemon('.$this->resource.'): '.$msg);
	}

	function subscribed($to) {
		$this->special_presence('subscribed', $to);
	}

	function special_presence($type, $to=NULL, $show=NULL, $status=NULL) {
		$to = htmlspecialchars($to);
		$status = htmlspecialchars($status);
		$out = "<presence";
		if($to) $out .= " to='$to'";
		if($type) $out .= " type='$type'";
		if($show == 'available' and !$status) {
			$out .= "/>";
		} else {
			$out .= ">";
			if($show && ($show != 'available')) $out .= "<show>$show</show>";
			if($status) $out .= "<status>$status</status>";
			$out .= "</presence>";
		}
		$this->conn->send($out);
	}
}

$daemon = new XMPPDaemon();

if ($daemon->connect()) {
	$daemon->handle();
}
?>
