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
	 * Spits out a Laconica notice as a Twitter-compatible "status"
	 */
	function show_xml_status($notice) {
		global $config;
		$profile = $notice->getProfile();
		
		common_element_start('status');
		// XXX: twitter created_at date looks like this: Mon Jul 14 23:52:38 +0000 2008
		common_element('created_at', NULL, common_exact_date($notice->created));
		common_element('text', NULL, $notice->content);
		common_element('source', NULL, 'Web');  # twitterific, twitterfox, etc.
		common_element('truncated', NULL, 'false'); # how do we tell in Laconica?
		common_element('in_reply_to_status_id', NULL, $notice->reply_to);
		common_element('in_reply_to_user_id', NULL,'');
		common_element('favorited', Null, '');  # feature for some day
		
		common_element_start('user');
		common_element('id', NULL, $notice->id);
		common_element('name', NULL, $profile->getBestName());
		common_element('screen_name', NULL, $profile->nickname);
		common_element('location', NULL, $profile->location);
		common_element('description', NULL, $profile->bio);
		
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		
		common_element('profile_image_url', NULL, ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE));
		common_element('url', NULL, $profile->homepage);
		common_element('protected', NULL, 'false'); # not supported yet
		common_element('followers_count', NULL, $this->count_subscriptions($profile)); # where do I get this?
		common_element_end('user');
		
		common_element_end('status');
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