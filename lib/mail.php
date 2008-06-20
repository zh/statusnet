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

require_once('Mail.php');

function mail_backend() {
	static $backend = NULL;

	if (!$backend) {
		global $config;
		$backend = Mail::factory($config['mail']['backend'],
								 ($config['mail']['params']) ? $config['mail']['params'] : array());
		if (PEAR::isError($backend)) {
			common_server_error($backend->getMessage(), 500);
		}
	}
	return $backend;
}

# XXX: use Mail_Queue... maybe

function mail_send($recipients, $headers, $body) {
	$backend = mail_backend();
	assert($backend); # throws an error if it's bad
	$sent = $backend->send($recipients, $headers, $body);
	if (PEAR::isError($sent)) {
		common_server_error($sent->getMessage(), 500);
	}
}

function mail_notify_from() {
	global $config;
	if ($config['mail']['notifyfrom']) {
		return $config['mail']['notifyfrom'];
	} else {
		return $config['site']['name'] . ' <noreply@'.$config['site']['server'].'>';
	}
}

# For confirming an email address

function mail_confirm_address($code, $nickname, $address) {
	$recipients = $address;
	$headers['From'] = mail_notify_from();
	$headers['To'] = $nickname . ' <' . $address . '>';
	$headers['Subject'] = _t('Email address confirmation');

	$body = "Hey, $nickname.";
	$body .= "\n\n";
	$body .= 'Someone just entered this email address on ' . common_config('site', 'name') . '.';
	$body .= "\n\n";
	$body .= 'If it was you, and you want to confirm your entry, use the URL below:';
	$body .= "\n\n";
	$body .= "\t".common_local_url('confirmemail',
								   array('code' => $code));
	$body .= "\n\n";
	$body .= 'If not, just ignore this message.';
	$body .= "\n\n";
	$body .= 'Thanks for your time, ';
	$body .= "\n";
	$body .= common_config('site', 'name');
	$body .= "\n";

	mail_send($recipients, $headers, $body);
}
