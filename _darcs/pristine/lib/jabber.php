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

require_once('XMPPHP/XMPP.php');

function jabber_valid_base_jid($jid) {
	# Cheap but effective
	return Validate::email($jid);
}

function jabber_normalize_jid($jid) {
	if (preg_match("/(?:([^\@]+)\@)?([^\/]+)(?:\/(.*))?$/", $jid, $matches)) {
		$node = $matches[1];
		$server = $matches[2];
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
		$conn = new XMPPHP_XMPP(common_config('xmpp', 'host') ?
								common_config('xmpp', 'host') :
								common_config('xmpp', 'server'),
								common_config('xmpp', 'port'),
								common_config('xmpp', 'user'),
								common_config('xmpp', 'password'),
								($resource) ? $resource :
								common_config('xmpp', 'resource'),
								common_config('xmpp', 'server'),
								common_config('xmpp', 'debug') ?
								true : false,
								common_config('xmpp', 'debug') ?
								XMPPHP_Log::LEVEL_VERBOSE :  NULL
								);

		if (!$conn) {
			return false;
		}

		$conn->autoSubscribe();
		$conn->useEncryption(common_config('xmpp', 'encryption'));

		try {
			$conn->connect(true); # true = persistent connection
		} catch (XMPPHP_Exception $e) {
			common_log(LOG_ERROR, $e->getMessage());
			return false;
		}

    	$conn->processUntil('session_start');
	}
	return $conn;
}

function jabber_send_notice($to, $notice) {
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$profile = Profile::staticGet($notice->profile_id);
	if (!$profile) {
		common_log(LOG_WARNING, 'Refusing to send notice with ' .
		           'unknown profile ' . common_log_objstring($notice),
		           __FILE__);
		return false;
	}
	$msg = jabber_format_notice($profile, $notice);
	$entry = jabber_format_entry($profile, $notice);
	$conn->message($to, $msg, 'chat', NULL, $entry);
	$profile->free();
	return true;
}

# Extra stuff defined by Twitter, needed by twitter clients

function jabber_format_entry($profile, $notice) {

	# FIXME: notice url might be remote

	$noticeurl = common_local_url('shownotice',
								  array('notice' => $notice->id));
	$msg = jabber_format_notice($profile, $notice);
	$entry = "\n<entry xmlns='http://www.w3.org/2005/Atom'>\n";
	$entry .= "<source>\n";
	$entry .= "<title>" . $profile->nickname . " - " . common_config('site', 'name') . "</title>\n";
	$entry .= "<link href='" . htmlspecialchars($profile->profileurl) . "'/>\n";
	$entry .= "<link rel='self' type='application/rss+xml' href='" . common_local_url('userrss', array('nickname' => $profile->nickname)) . "'/>\n";
	$entry .= "<author><name>" . $profile->nickname . "</name></author>\n";
	$entry .= "<icon>" . common_profile_avatar_url($profile, AVATAR_PROFILE_SIZE) . "</icon>\n";
	$entry .= "</source>\n";
	$entry .= "<title>" . htmlspecialchars($msg) . "</title>\n";
	$entry .= "<summary>" . htmlspecialchars($msg) . "</summary>\n";
	$entry .= "<link rel='alternate' href='" . $noticeurl . "' />\n";
	$entry .= "<id>". $notice->uri . "</id>\n";
	$entry .= "<published>".common_date_w3dtf($notice->created)."</published>\n";
	$entry .= "<updated>".common_date_w3dtf($notice->modified)."</updated>\n";
	$entry .= "</entry>\n";

	$html = "\n<html xmlns='http://jabber.org/protocol/xhtml-im'>\n";
	$html .= "<body xmlns='http://www.w3.org/1999/xhtml'>\n";
	$html .= "<a href='".htmlspecialchars($profile->profileurl)."'>".$profile->nickname."</a>: ";
	$html .= ($notice->rendered) ? $notice->rendered : common_render_content($notice->content, $notice);
	$html .= "\n</body>\n";
	$html .= "\n</html>\n";

	$address = "<addresses xmlns='http://jabber.org/protocol/address'>\n";
	$address .= "<address type='replyto' jid='" . jabber_daemon_address() . "' />\n";
	$address .= "</addresses>\n";

	# FIXME: include a pubsub event, too.

	return $html . $entry . $address;
}

function jabber_send_message($to, $body, $type='chat', $subject=NULL) {
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$conn->message($to, $body, $type, $subject);
	return true;
}

function jabber_send_presence($status, $show='available', $to=NULL,
							  $type = 'available', $priority=NULL)
{
	$conn = jabber_connect();
	if (!$conn) {
		return false;
	}
	$conn->presence($status, $show, $to, $type, $priority);
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

	return jabber_send_message($address, $body);
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

	if (!common_config('xmpp', 'enabled')) {
		return true;
	}
	$profile = Profile::staticGet($notice->profile_id);

	if (!$profile) {
		common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
		           'unknown profile ' . common_log_objstring($notice),
		           __FILE__);
		return false;
	}

	$msg = jabber_format_notice($profile, $notice);
	$entry = jabber_format_entry($profile, $notice);

	$profile->free();
	unset($profile);

	$sent_to = array();
	$conn = jabber_connect();

	# First, get users to whom this is a direct reply
	$user = new User();
	$user->query('SELECT user.id, user.jabber ' .
				 'FROM user JOIN reply ON user.id = reply.profile_id ' .
				 'WHERE reply.notice_id = ' . $notice->id . ' ' .
				 'AND user.jabber is not null ' .
				 'AND user.jabbernotify = 1 ' .
				 'AND user.jabberreplies = 1 ');

	while ($user->fetch()) {
		common_log(LOG_INFO,
				   'Sending reply notice ' . $notice->id . ' to ' . $user->jabber,
				   __FILE__);
		$conn->message($user->jabber, $msg, 'chat', NULL, $entry);
		$conn->processTime(0);
		$sent_to[$user->id] = 1;
	}

	$user->free();

    # Now, get users subscribed to this profile

	$user = new User();
	$user->query('SELECT user.id, user.jabber ' .
				 'FROM user JOIN subscription ON user.id = subscription.subscriber ' .
				 'WHERE subscription.subscribed = ' . $notice->profile_id . ' ' .
				 'AND user.jabber is not null ' .
				 'AND user.jabbernotify = 1 ' .
                 'AND subscription.jabber = 1 ');

	while ($user->fetch()) {
		if (!array_key_exists($user->id, $sent_to)) {
			common_log(LOG_INFO,
					   'Sending notice ' . $notice->id . ' to ' . $user->jabber,
					   __FILE__);
			$conn->message($user->jabber, $msg, 'chat', NULL, $entry);
			# To keep the incoming queue from filling up, we service it after each send.
			$conn->processTime(0);
		}
	}

	$user->free();

	return true;
}

function jabber_public_notice($notice) {

	# Now, users who want everything

	$public = common_config('xmpp', 'public');

	# FIXME PRIV don't send out private messages here
	# XXX: should we send out non-local messages if public,localonly
	# = false? I think not

	if ($public && $notice->is_local) {
		$profile = Profile::staticGet($notice->profile_id);

		if (!$profile) {
			common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
					   'unknown profile ' . common_log_objstring($notice),
					   __FILE__);
			return false;
		}

		$msg = jabber_format_notice($profile, $notice);
		$entry = jabber_format_entry($profile, $notice);

		$conn = jabber_connect();

		foreach ($public as $address) {
			common_log(LOG_INFO,
					   'Sending notice ' . $notice->id . ' to public listener ' . $address,
					   __FILE__);
			$conn->message($address, $msg, 'chat', NULL, $entry);
			$conn->processTime(0);
		}
		$profile->free();
	}

	return true;
}

function jabber_format_notice(&$profile, &$notice) {
	return $profile->nickname . ': ' . $notice->content;
}
