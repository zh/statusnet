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

define('INSTALLDIR', dirname(__FILE__));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/mail.php');
require_once('Mail/mimeDecode.php');

class MailerDaemon {
	
	function __construct() {
	}
	
	function handle_message($fname='php://stdin') {
		list($from, $to, $msg) = $this->parse_message($fname);
		if (!$from || !$to || !$msg) {
			$this->error(NULL, _t('Could not parse message.'));
		}
		$user = User::staticGet('email', common_canonical_email($from));
		if (!$user) {
			$this->error($from, _('Not a registered user.'));
			return false;
		}
		if ($user->incomingemail != common_canonical_email($to)) {
			$this->error($from, _('Sorry, that is not your incoming email address.'));
		}
		$response = $this->handle_command($user, $msg);
		if ($response) {
			$this->respond($from, $to, $response);
		}
		$this->add_notice($user, $msg);
	}

	function error($from, $msg) {
		file_put_contents("php://stderr", $msg);
		exit(1);
	}

	function handle_command($user, $msg) {
		return false;
	}
	
	function respond($from, $to, $response) {

		$headers['From'] = $to;
		$headers['To'] = $from;
		$headers['Subject'] = "Command complete";

		return mail_send(array($from), $headers, $response);
	}
	
	function log($level, $msg) {
		common_log($level, 'MailDaemon: '.$msg);
	}
	
	function add_notice($user, $msg) {
		$notice = new Notice();
		$notice->profile_id = $user->id;
		$notice->content = trim(substr($msg, 0, 140));
		$notice->rendered = common_render_content($notice->content, $notice);
		$notice->created = DB_DataObject_Cast::dateTime();
		$notice->query('BEGIN');
		$id = $notice->insert();
		if (!$id) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			$this->log(LOG_ERR,
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
			$this->log(LOG_ERR,
					   'Could not add URI to ' . common_log_objstring($notice) .
					   ' for user ' . common_log_objstring($user) .
					   ': ' . $last_error->message);
			return;
		}
		$notice->query('COMMIT');
        common_save_replies($notice);	
		common_real_broadcast($notice);
		$this->log(LOG_INFO,
				   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
	}
	
	function parse_message($fname) {
		$contents = file_get_contents($fname);
		$parsed = Mail_mimeDecode::decode(array('input' => $contents,
												'include_bodies' => true,
												'decode_headers' => true,
												'decode_bodies' => true));
		if (!$parsed) {
			return NULL;
		}
		$from = $parsed->headers['from'];
		$to = $parsed->headers['to'];

		$type = $parsed->ctype_primary . '/' . $parsed->ctype_secondary;
		
		if ($parsed->ctype_primary == 'multitype') {
			foreach ($parsed->parts as $part) {
				if ($part->ctype_primary == 'text' &&
					$part->ctype_secondary == 'plain') {
					$msg = $part->body;
					break;
				}
			}
		} else if ($type == 'text/plain') {
			$msg = $parsed->body;
		} else {
			$this->unsupported_type($parsed);
		}
		
		return array($from, $to, $msg);
	}
	
	function unsupported_type($parsed) {
		$this->error(NULL, "Unsupported message type: " . $parsed->ctype_primary . "/" . $parsed->ctype_secondary ."\n");
	}
}

$md = new MailerDaemon();
$md->handle_message('php://stdin');