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
		common_log(LOG_ERR, 'Email error: ' . $sent->getMessage());
		return false;
	}
	return true;
}

function mail_notify_from() {
	global $config;
	if ($config['mail']['notifyfrom']) {
		return $config['mail']['notifyfrom'];
	} else {
		return $config['site']['name'] . ' <noreply@'.$config['site']['server'].'>';
	}
}

function mail_to_user(&$user, $subject, $body, $address=NULL) {
	if (!$address) {
		$address = $user->email;
	}

	$recipients = $address;
	$profile = $user->getProfile();

	$headers['From'] = mail_notify_from();
	$headers['To'] = $profile->getBestName() . ' <' . $address . '>';
	$headers['Subject'] = $subject;

	return mail_send($recipients, $headers, $body);
}

# For confirming a Jabber address
# XXX: change to use mail_to_user() above

function mail_confirm_address($code, $nickname, $address) {
	$recipients = $address;
	$headers['From'] = mail_notify_from();
	$headers['To'] = $nickname . ' <' . $address . '>';
	$headers['Subject'] = _('Email address confirmation');

	$body = "Hey, $nickname.";
	$body .= "\n\n";
	$body .= 'Someone just entered this email address on ' . common_config('site', 'name') . '.';
	$body .= "\n\n";
	$body .= 'If it was you, and you want to confirm your entry, use the URL below:';
	$body .= "\n\n";
	$body .= "\t".common_local_url('confirmaddress',
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

function mail_subscribe_notify($listenee, $listener) {
	if ($listenee->email && $listenee->emailnotifysub) {
		$profile = $listenee->getProfile();
		$other = $listener->getProfile();
		$name = $profile->getBestName();
		$long_name = ($other->fullname) ? ($other->fullname . ' (' . $other->nickname . ')') : $other->nickname;
		$recipients = $listenee->email;
		$headers['From'] = mail_notify_from();
		$headers['To'] = $name . ' <' . $listenee->email . '>';
		$headers['Subject'] = sprintf(_('%1$s is now listening to your notices on %2$s.'), $other->getBestName(),
									  common_config('site', 'name'));
		$body  = sprintf(_('%1$s is now listening to your notices on %2$s.'."\n\n".
						   "\t".'%3$s'."\n\n".
						   'Faithfully yours,'."\n".'%4$s.'."\n"),
						 $long_name,
						 common_config('site', 'name'), 
						 $other->profileurl,
						 common_config('site', 'name'));
		mail_send($recipients, $headers, $body);
	}
}
