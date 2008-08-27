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

# XXX: something of a hack to work around problems with the XMPPHP lib

class Laconica_XMPP extends XMPPHP_XMPP {
    
    function messageplus($to, $body, $type = 'chat', $subject = null, $payload = null) {
		$to	  = htmlspecialchars($to);
		$body	= htmlspecialchars($body);
		$subject = htmlspecialchars($subject);
		
		$jid = jabber_daemon_address();
		
		$out = "<message from='$jid' to='$to' type='$type'>";
		if($subject) $out .= "<subject>$subject</subject>";
		$out .= "<body>$body</body>";
		if($payload) $out .= $payload;
		$out .= "</message>";
		
		$cnt = strlen($out);
		common_log(LOG_DEBUG, "Sending $cnt chars to $to");
		$this->send($out);
		common_log(LOG_DEBUG, 'Done.');
    }
	
	public function presence($status = null, $show = 'available', $to = null, $type='available', $priority=NULL) {
		if($type == 'available') $type = '';
		$to	 = htmlspecialchars($to);
		$status = htmlspecialchars($status);
		if($show == 'unavailable') $type = 'unavailable';
		
		$out = "<presence";
		if($to) $out .= " to='$to'";
		if($type) $out .= " type='$type'";
		if($show == 'available' and !$status and is_null($priority)) {
			$out .= "/>";
		} else {
			$out .= ">";
			if($show != 'available') $out .= "<show>$show</show>";
			if($status) $out .= "<status>$status</status>";
			if(!is_null($priority)) $out .= "<priority>$priority</priority>";
			$out .= "</presence>";
		}
		
		$this->send($out);
	}
}

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

function jabber_connect($resource=NULL, $status=NULL, $priority=NULL) {
	static $conn = NULL;
	if (!$conn) {
		$conn = new Laconica_XMPP(common_config('xmpp', 'host') ?
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
		$conn->autoSubscribe();
		$conn->useEncryption(common_config('xmpp', 'encryption'));
		
		if (!$conn) {
			return false;
		}
		$conn->connect(true); # true = persistent connection
		if ($conn->isDisconnected()) {
			return false;
		}
    	$conn->processUntil('session_start');
		$conn->getRoster();
		$conn->presence($presence, $priority);
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
	$conn->messageplus($to, $msg, 'chat', NULL, $entry);
	return true;
}

# Extra stuff defined by Twitter, needed by twitter clients

function jabber_format_entry($profile, $notice) {
	
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
	$html .= "<a href='".common_profile_url($profile->nickname)."'>".$profile->nickname."</a>: ";
	$html .= ($notice->rendered) ? $notice->rendered : common_render_content($notice->content, $notice);
	$html .= "\n</body>\n";
	$html .= "\n</html>\n";
	
	$event = "<event xmlns='http://jabber.org/protocol/pubsub#event'>\n";
    $event .= "<items xmlns='http://jabber.org/protocol/pubsub' ";
	$event .= "node='" . common_local_url('public') . "'>\n";
	$event .= "<item id='" . $notice->uri ."' />\n";
	$event .= "</items>\n";
	$event .= "</event>\n";
	# FIXME: include the pubsub event, too.
	return $html . $entry;
#	return $entry . "\n" . $event;
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
	$sent_to = array();
	# First, get users who this is a direct reply to
	$reply = new Reply();
	$reply->notice_id = $notice->id;
	if ($reply->find()) {
		while ($reply->fetch()) {
			$user = User::staticGet($reply->profile_id);
			if ($user && $user->jabber && $user->jabbernotify && $user->jabberreplies) {
				common_log(LOG_INFO,
						   'Sending reply notice ' . $notice->id . ' to ' . $user->jabber,
						   __FILE__);
				$success = jabber_send_notice($user->jabber, $notice);
				if ($success) {
					# Remember so we don't send twice
					$sent_to[$user->id] = true;
				} else {
					# XXX: Not sure, but I think that's the right thing to do
					common_log(LOG_WARNING,
							   'Sending reply notice ' . $notice->id . ' to ' . $user->jabber . ' FAILED, cancelling.',
							   __FILE__);
					return false;
				}
			}
		}
	}
    # Now, get users subscribed to this profile
	# XXX: use a join here rather than looping through results
	$sub = new Subscription();
	$sub->subscribed = $notice->profile_id;
        
	if ($sub->find()) {
		while ($sub->fetch()) {
			$user = User::staticGet($sub->subscriber);
			if ($user && $user->jabber && $user->jabbernotify && !array_key_exists($user->id,$sent_to)) {
				common_log(LOG_INFO,
						   'Sending notice ' . $notice->id . ' to ' . $user->jabber,
						   __FILE__);
				$success = jabber_send_notice($user->jabber, $notice);
				if ($success) {
					$sent_to[$user->id] = true;
				} else {
					# XXX: Not sure, but I think that's the right thing to do
					common_log(LOG_WARNING,
							   'Sending notice ' . $notice->id . ' to ' . $user->jabber . ' FAILED, cancelling.',
							   __FILE__);
					return false;
				}
			}
		}
	}

	# Now, users who want everything
	
	$public = common_config('xmpp', 'public');
	
	# FIXME PRIV don't send out private messages here
	# XXX: should we send out non-local messages if public,localonly = false? I think not
	
	if ($public && $notice->is_local) {
		foreach ($public as $address) {
				common_log(LOG_INFO,
						   'Sending notice ' . $notice->id . ' to public listener ' . $address,
						   __FILE__);
				jabber_send_notice($address, $notice);
		}
	}
	
	return true;
}

function jabber_format_notice(&$profile, &$notice) {
	return $profile->nickname . ': ' . $notice->content;
}
