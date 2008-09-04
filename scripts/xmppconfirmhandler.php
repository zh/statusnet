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
require_once(INSTALLDIR . '/lib/xmppqueuehandler.php');

set_error_handler('common_error_handler');

define('CLAIM_TIMEOUT', 1200);

class XmppConfirmHandler extends XmppQueueHandler {

	var $_id = 'confirm';
	
	function class_name() {
		return 'XmppConfirmHandler';
	}
	
	function run() {
		if (!$this->start()) {
			return false;
		}
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
						common_log_db_error($confirm, 'UPDATE', __FILE__);
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
		if (!$this->finish()) {
			return false;
		}
		return true;
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
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

$resource = ($argc > 1) ? $argv[1] : (common_config('xmpp', 'resource').'-confirm');

$handler = new XmppConfirmHandler($resource);

$handler->runOnce();

