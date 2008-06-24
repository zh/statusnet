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

require_once('xmpp.php');

function jabber_valid_base_jid($jid) {
	# Cheap but effective
	return Validate::email($jid);
}

function jabber_normalize_jid($jid) {
	preg_match("/(?:([^\@]+)\@)?([^\/]+)(?:\/(.*))?$/", $jid, $matches);
	$node = $matches[1];
	$server = $matches[2];
	$resource = $matches[3];
	return strtolower($node.'@'.$server);
}

function jabber_connect($resource=NULL) {
	static $conn = NULL;
	if (!$conn) {
		$conn = new XMPP(common_config('xmpp', 'server'),
					     common_config('xmpp', 'port'),
					     common_config('xmpp', 'user'),
					     common_config('xmpp', 'password'),
				    	 ($resource) ? $resource : 
				        	common_config('xmpp', 'resource'));
				        
		if (!$conn) {
			return false;
		}
		$conn->connect(true); # try to get a persistent connection
		if ($conn->disconnected) {
			return false;
		}
        $conn->processUntil('session_start');
	}
	return $conn;
}

function jabber_send_message($to, $body, $type='chat', $subject=NULL) {
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$conn->message($to, $body, $type, $subject);
	return true;
}

function jabber_send_presence($status=Null, $show='available', $to=Null) {
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$conn->presence($status, $show, $to);
	return true;
}

function jabber_confirm_address($code, $nickname, $address) {

	# FIXME: do we have to request presence first?
	
	$body = "Hey, $nickname.";
	$body .= "\n\n";
	$body .= 'Someone just entered this IM address on ';
	$body .= common_config('site', 'name') . '.';
	$body .= "\n\n";
	$body .= 'If it was you, and you want to confirm your entry, ';
	$body .= 'use the URL below:';
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

	jabber_send_message($address, $body);
}
	
function jabber_broadcast_notice($notice) {
	# First, get users subscribed to this profile
	# XXX: use a join here rather than looping through results
	$profile = Profile::staticGet($notice->profile_id);
	if (!$profile) {
		common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
		           'unknown profile ' . common_log_objstring($notice),
		           __FILE__);
		return false;
	}
	$sub = new Subscription();
	$sub->subscribed = $notice->profile_id;
	if ($sub->find()) {
		$msg = jabber_format_notice($profile, $notice);
		while ($sub->fetch()) {
			$user = User::staticGet($sub->subscriber);
			if ($user && $user->jabber) {
				jabber_send_message($user->jabber,
				                    $msg);
			}
		}
	}
}

function jabber_format_notice(&$profile, &$notice) {
	return = $profile->nickname . ': ' . $notice->content;
}
