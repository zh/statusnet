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

if (!defined('LACONICA')) { exit(1); }

class Channel {
	
	function on($user) {
		return false;
	}

	function off($user) {
		return false;
	}

	function output($user, $text) {
		return false;
	}
	
	function error($user, $text) {
		return false;
	}
	
	function source() {
		return NULL;
	}
}

class XMPPChannel extends Channel {

	var $conn = NULL;
	
	function source() {
		return 'xmpp';
	}
	
	function __construct($conn) {
		$this->conn = $conn;
	}
	
	function on($user) {
		return $this->set_notify($user, 1);
	}
	
	function off($user) {
		return $this->set_notify($user, 0);
	}

	function output($user, $text) {
		$text = '['.common_config('site', 'name') . '] ' . $text;
		jabber_send_message($user->jabber, $text);
	}
	
	function error($user, $text) {
		$text = '['.common_config('site', 'name') . '] ' . $text;
		jabber_send_message($user->jabber, $text);
	}
	
	function set_notify(&$user, $notify) {
		$orig = clone($user);
		$user->jabbernotify = $notify;
		$result = $user->update($orig);
		if (!$result) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			common_log(LOG_ERR,
					   'Could not set notify flag to ' . $notify .
					   ' for user ' . common_log_objstring($user) .
					   ': ' . $last_error->message);
			return false;
		} else {
			common_log(LOG_INFO,
					   'User ' . $user->nickname . ' set notify flag to ' . $notify);
			return true;
		}
	}
}


class WebChannel extends Channel {

	function source() {
		return 'web';
	}
	
	function on($user) {
		return false;
	}
	
	function off($user) {
		return false;
	}

	function output($user, $text) {
		# XXX: buffer all output and send it at the end
		# XXX: even better, redirect to appropriate page
		#      depending on what command was run
		common_show_header(_('Command results'));
		common_element('p', NULL, $text);
		common_show_footer();
	}
	
	function error($user, $text) {
		common_user_error($text);
	}
}

class MailChannel extends Channel {

	var $addr = NULL;

	function source() {
		return 'mail';
	}
	
	function __construct($addr=NULL) {
		$this->addr = $addr;
	}
	
	function on($user) {
		return $this->set_notify($user, 1);
	}
	
	function off($user) {
		return $this->set_notify($user, 0);
	}

	function output($user, $text) {

		$headers['From'] = $user->incomingemail;
		$headers['To'] = $this->addr;
		
		$headers['Subject'] = _('Command complete');

		return mail_send(array($this->addr), $headers, $text);
	}
	
	function error($user, $text) {
		
		$headers['From'] = $user->incomingemail;
		$headers['To'] = $this->addr;
		
		$headers['Subject'] = _('Command failed');

		return mail_send(array($this->addr), $headers, $text);
	}
	
	function set_notify($user, $value) {
		$orig = clone($user);
		$user->smsnotify = $value;
		$result = $user->update($orig);
		if (!$result) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			return false;
		}
		return true;
	}
}
