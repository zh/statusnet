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
	if (preg_match("/(?:([^\@]+)\@)?([^\/]+)(?:\/(.*))?$/", $jid, $matches)) {
		$node = $matches[1];
		$server = $matches[2];
		$resource = $matches[3];
		return strtolower($node.'@'.$server);
	} else {
		return NULL;
	}
}

function jabber_daemon_address() {
	return common_config('xmpp', 'user') . '@' . common_config('xmpp', 'server');
}

function jabber_connect($resource=NULL) {
	static $conn = NULL;
	if (!$conn) {
		$conn = new XMPP(common_config('xmpp', 'host') ?
				         common_config('xmpp', 'host') :
				         common_config('xmpp', 'server'),
				         common_config('xmpp', 'port'),
					     common_config('xmpp', 'user'),
					     common_config('xmpp', 'password'),
				    	 ($resource) ? $resource :
				        	common_config('xmpp', 'resource'),
				         common_config('xmpp', 'server'));

		if (!$conn) {
			return false;
		}
		$conn->connect(true); # true = persistent connection
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

function jabber_send_presence($status, $show='available', $to=Null) {
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$conn->presence($status, $show, $to);
	return true;
}

function jabber_confirm_address($code, $nickname, $address) {
	$body = 'User "' . $nickname . '" on ' . common_config('site', 'name') . ' ' .
			'has said that your Jabber ID belongs to them. ' .
    	    'If that\'s true, you can confirm by clicking on this URL: ' .
        	common_local_url('confirmaddress', array('code' => $code)) .
        	' . (If you cannot click it, copy-and-paste it into the ' .
        	'address bar of your browser). If that user isn\'t you, ' .
        	'or if you didn\'t request this confirmation, just ignore this message.';

	jabber_send_message($address, $body);
}

function jabber_special_presence($type, $to=NULL, $show=NULL, $status=NULL) {
	$conn = jabber_connect();

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
	$conn->send($out);
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
			if ($user && $user->jabber && $user->jabbernotify) {
				common_log(LOG_INFO, 
						   'Sending notice ' . $notice->id . ' to ' . $user->jabber,
						   __FILE__);
				$success = jabber_send_message($user->jabber, $msg);
				if (!$success) {
					# XXX: Not sure, but I think that's the right thing to do
					return false;
				}
			}
		}
	}
	return true;
}

function jabber_format_notice(&$profile, &$notice) {
	return $profile->nickname . ': ' . $notice->content;
}
