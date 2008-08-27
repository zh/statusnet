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

	var $_id = 'generic';
	
	function XmppConfirmHandler($id=NULL) {
		if ($id) {
			$this->_id = $id;
		}
	}
		  
	function handle_queue() {
		$this->log(LOG_INFO, 'checking for queued confirmations');
		$cnt = 0;
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
				$cnt++;
			} else {
				$this->clear_old_confirm_claims();
				sleep(10);
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
}

mb_internal_encoding('UTF-8');

$resource = ($argc > 1) ? $argv[1] : NULL;

$handler = new XmppConfirmHandler($resource);

if ($handler->start()) {
	$handler->handle_queue();
}

$handler->finish();
