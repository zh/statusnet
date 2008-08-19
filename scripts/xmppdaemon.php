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

function xmppdaemon_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
    switch ($errno) {
     case E_USER_ERROR:
	echo "ERROR: [$errno] $errstr ($errfile:$errline)\n";
	echo "  Fatal error on line $errline in file $errfile";
	echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")\n";
	echo "Aborting...\n";
	exit(1);
	break;

    case E_USER_WARNING:
	echo "WARNING [$errno] $errstr ($errfile:$errline)\n";
	break;

     case E_USER_NOTICE:
	echo "My NOTICE [$errno] $errstr ($errfile:$errline)\n";
	break;

     default:
	echo "Unknown error type: [$errno] $errstr ($errfile:$errline)\n";
	break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}

set_error_handler('xmppdaemon_error_handler');

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
	print "This script must be run from the command line\n";
	exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/jabber.php');

# This is kind of clunky; we create a class to call the global functions
# in jabber.php, which create a new XMPP class. A more elegant (?) solution
# might be to use make this a subclass of XMPP.

class XMPPDaemon {

	function XMPPDaemon($resource=NULL) {
		static $attrs = array('server', 'port', 'user', 'password', 'host');

		foreach ($attrs as $attr)
		{
			$this->$attr = common_config('xmpp', $attr);
		}

		if ($resource) {
			$this->resource = $resource;
		} else {
			$this->resource = common_config('xmpp', 'resource') . 'daemon';
		}

		$this->log(LOG_INFO, "{$this->user}@{$this->server}/{$this->resource}");
	}

	function connect() {

		$connect_to = ($this->host) ? $this->host : $this->server;

		$this->log(LOG_INFO, "Connecting to $connect_to on port $this->port");

		$this->conn = jabber_connect($this->resource);

		if (!$this->conn) {
			return false;
		}

		return !$this->conn->isDisconnected();
	}

	function handle() {

		static $parts = array('message', 'presence',
							  'end_stream', 'session_start');

		while(!$this->conn->isDisconnected()) {

			$payloads = $this->conn->processUntil($parts, 10);

			if ($payloads) {
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

			$this->broadcast_queue();
			$this->confirmation_queue();
		}
	}

	function handle_session($pl) {
		# XXX what to do here?
		return true;
	}

	function get_user($from) {
		$user = User::staticGet('jabber', jabber_normalize_jid($from));
		return $user;
	}

	function get_confirmation($from) {
		$confirm = new Confirm_address();
		$confirm->address = $from;
		$confirm->address_type = 'jabber';
		if ($confirm->find(TRUE)) {
			return $confirm;
		} else {
			return NULL;
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
		$user = $this->get_user($from);

		if (!$user) {
			$this->from_site($from, 'Unknown user; go to ' .
							 common_local_url('imsettings') .
							 ' to add your address to your account');
			$this->log(LOG_WARNING, 'Message from unknown user ' . $from);
			return;
		}
		if ($this->handle_command($user, $pl['body'])) {
			return;
		} else if ($this->is_autoreply($pl['body'])) {
			$this->log(LOG_INFO, 'Ignoring auto reply from ' . $from);
			return;
		} else if ($this->is_otr($pl['body'])) {
			$this->log(LOG_INFO, 'Ignoring OTR from ' . $from);
			return;
		} else {
			if(strlen($pl['body'])>140) {
				$this->from_site($from, 'Message too long - maximum is 140 characters, you sent ' . strlen($pl['body']));
				return;
			}
			$this->add_notice($user, $pl);
		}
	}

	function is_autoreply($txt) {
		if (preg_match('/[\[\(]?[Aa]uto-?[Rr]eply[\]\)]/', $txt)) {
			return true;
		} else {
			return false;
		}
	}

	function is_otr($txt) {
		if (preg_match('/^\?OTR/', $txt)) {
			return true;
		} else {
			return false;
		}
	}
	
	function from_site($address, $msg) {
		$text = '['.common_config('site', 'name') . '] ' . $msg;
		jabber_send_message($address, $text);
	}

	function handle_command($user, $body) {
		# XXX: localise
		switch(trim($body)) {
		 case 'on':
			$this->set_notify($user, true);
			$this->from_site($user->jabber, 'notifications on');
			return true;
		 case 'off':
			$this->set_notify($user, false);
			$this->from_site($user->jabber, 'notifications off');
			return true;
		 default:
			return false;
		}
	}

	function set_notify(&$user, $notify) {
		$orig = clone($user);
		$user->jabbernotify = $notify;
		$result = $user->update($orig);
		if (!$result) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			$this->log(LOG_ERR,
					   'Could not set notify flag to ' . $notify .
					   ' for user ' . common_log_objstring($user) .
					   ': ' . $last_error->message);
		} else {
			$this->log(LOG_INFO,
					   'User ' . $user->nickname . ' set notify flag to ' . $notify);
		}
	}

	function add_notice(&$user, &$pl) {
		$notice = Notice::saveNew($user->id, trim(mb_substr($pl['body'], 0, 140)), 'xmpp');
		if (is_string($notice)) {
			$this->log(LOG_ERR, $notice);
			return;
		}
		common_real_broadcast($notice);
		$this->log(LOG_INFO,
				   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
	}

	function handle_presence(&$pl) {
		$from = jabber_normalize_jid($pl['from']);
		switch ($pl['type']) {
		 case 'subscribe':
			# We let anyone subscribe
			$this->subscribed($from);
			$this->log(LOG_INFO,
					   'Accepted subscription from ' . $from);
			break;
		 case 'subscribed':
		 case 'unsubscribed':
		 case 'unsubscribe':
			$this->log(LOG_INFO,
					   'Ignoring  "' . $pl['type'] . '" from ' . $from);
			break;
		 default:
			if (!$pl['type']) {
				$user = User::staticGet('jabber', $from);
				if (!$user) {
					$this->log(LOG_WARNING, 'Presence from unknown user ' . $from);
					return;
				}
				if ($user->updatefrompresence) {
					$this->log(LOG_INFO, 'Updating ' . $user->nickname .
							   ' status from presence.');
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
		jabber_special_presence('subscribed', $to);
	}

	function set_status($status) {
		$this->log(LOG_INFO, 'Setting status to "' . $status . '"');
		jabber_send_presence($status);
	}

	function top_queue_item() {

		$qi = new Queue_item();
		$qi->orderBy('created');
		$qi->whereAdd('claimed is NULL');

		$qi->limit(1);

		$cnt = $qi->find(TRUE);

		if ($cnt) {
			# XXX: potential race condition
			# can we force it to only update if claimed is still NULL
			# (or old)?
			$this->log(LOG_INFO, 'claiming queue item = ' . $qi->notice_id);
			$orig = clone($qi);
			$qi->claimed = DB_DataObject_Cast::dateTime();
			$result = $qi->update($orig);
			if ($result) {
				$this->log(LOG_INFO, 'claim succeeded.');
				return $qi;
			} else {
				$this->log(LOG_INFO, 'claim failed.');
			}
		}
		$qi = NULL;
		return NULL;
	}

	function broadcast_queue() {
		$this->clear_old_claims();
		$this->log(LOG_INFO, 'checking for queued notices');
		do {
			$qi = $this->top_queue_item();
			if ($qi) {
				$this->log(LOG_INFO, 'Got item enqueued '.common_exact_date($qi->created));
				$notice = Notice::staticGet($qi->notice_id);
				if ($notice) {
					$this->log(LOG_INFO, 'broadcasting notice ID = ' . $notice->id);
					# XXX: what to do if broadcast fails?
					$result = common_real_broadcast($notice, $this->is_remote($notice));
					if (!$result) {
						$this->log(LOG_WARNING, 'Failed broadcast for notice ID = ' . $notice->id);
						$orig = $qi;
						$qi->claimed = NULL;
						$qi->update($orig);
						$this->log(LOG_WARNING, 'Abandoned claim for notice ID = ' . $notice->id);
						continue;
					}
					$this->log(LOG_INFO, 'finished broadcasting notice ID = ' . $notice->id);
					$notice = NULL;
				} else {
					$this->log(LOG_WARNING, 'queue item for notice that does not exist');
				}
				$qi->delete();
			}
		} while ($qi);
	}

	function clear_old_claims() {
		$qi = new Queue_item();
	        $qi->claimed = NULL;
		$qi->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
		$qi->update(DB_DATAOBJECT_WHEREADD_ONLY);
	}

	function is_remote($notice) {
		$user = User::staticGet($notice->profile_id);
		return !$user;
	}

	function confirmation_queue() {
	    # $this->clear_old_confirm_claims();
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
			}
		} while ($confirm);
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
			$confirm->claimed = DB_DataObject_Cast::dateTime();
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

}

mb_internal_encoding('UTF-8');

$resource = ($argc > 1) ? $argv[1] : NULL;

$daemon = new XMPPDaemon($resource);

if ($daemon->connect()) {
	$daemon->set_status("Send me a message to post a notice");
	$daemon->handle();
}

?>
