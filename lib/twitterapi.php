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

class TwitterapiAction extends Action {

	function handle($args) {
		parent::handle($args);
	}
	
	/*
	 * Spits out a Laconica Notice as a Twitter-XML "status" 
	 */
	function render_xml_status($notice) {
		global $config;
	
		common_element_start('status');
		common_element('created_at', NULL, $this->date_twitter($notice->created));
		common_element('id', NULL, $notice->id);
		common_element('text', NULL, $notice->content);
		common_element('source', NULL, '');  # XXX: twitterific, twitterfox, etc. Not supported yet.
		common_element('truncated', NULL, 'false'); # Not possible on Laconica
		common_element('in_reply_to_status_id', NULL, $notice->reply_to);
		common_element('in_reply_to_user_id', NULL, ($notice->reply_to) ? $this->replier_by_reply($notice->reply_to) : '');
		common_element('favorited', Null, '');  # XXX: Not implemented on Laconica yet.

		$profile = $notice->getProfile();		
		$this->render_xml_user($profile);
		
		common_element_end('status');
	}	
	
	/*
	 * Spits out a Laconica Profile as a Twitter-XML "user"
	 */
	function render_xml_user($profile) {
		common_element_start('user');
		common_element('id', NULL, $profile->id);
		common_element('name', NULL, $profile->getBestName());
		common_element('screen_name', NULL, $profile->nickname);
		common_element('location', NULL, $profile->location);
		common_element('description', NULL, $profile->bio);
		
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		
		common_element('profile_image_url', NULL, ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE));
		common_element('url', NULL, $profile->homepage);
		common_element('protected', NULL, 'false'); # not supported by Laconica yet
		common_element('followers_count', NULL, $this->count_subscriptions($profile));
		common_element_end('user');
	}
	
	// Anyone know what date format this is?  It's not RFC 2822 
	// Twitter's dates look like this: "Mon Jul 14 23:52:38 +0000 2008" -- Zach 
	function date_twitter($dt) {
		$t = strtotime($dt);
		return date("D M d G:i:s O Y", $t);
	}

	function replier_by_reply($reply_id) {	

		$notice = Notice::staticGet($reply_id);
	
		if (!$notice) {
			common_debug("Got a bad notice_id: $reply_id");
		}

		$profile = $notice->getProfile();
		
		if (!$profile) {
			common_debug("Got a bad profile_id: $profile_id");
			return false;
		}
		
		return $profile->id;		
	}

	// XXX: Candidate for a general utility method somewhere?	
	function count_subscriptions($profile) {
		
		$count = 0;
		$sub = new Subscription();
		$sub->subscribed = $profile->id;

		if ($sub->find()) {
			while ($sub->fetch()) {
				if ($sub->token) {
					$other = Remote_profile::staticGet('id', $sub->subscriber);
				} else {
					$other = User::staticGet('id', $sub->subscriber);
				}
				if (!$other) {
					common_debug('Got a bad subscription: '.print_r($sub,TRUE));
					continue;
				}		
				$count++;
			}
		}
		return $count;
	}
	
}