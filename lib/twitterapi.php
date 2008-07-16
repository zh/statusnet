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
	
	function twitter_user_array($profile) {
		
		$twitter_user = array();

		$twitter_user['name'] = $profile->getBestName();		
		$twitter_user['followers_count'] = $this->count_subscriptions($profile);
		$twitter_user['screen_name'] = $profile->nickname;
		$twitter_user['description'] = ($profile->bio) ? $profile->bio : NULL;
		$twitter_user['location'] = ($profile->location) ? $profile->location : NULL;
		$twitter_user['id'] = intval($profile->id);
		
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		
		$twitter_user['profile_image_url'] = ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE);
		$twitter_user['protected'] = false; # not supported by Laconica yet
		$twitter_user['url'] = ($profile->homepage) ? $profile->homepage : NULL;
		
		return $twitter_user;		
	}

	function twitter_status_array($notice) {
		
		$twitter_status = array();

		$twitter_status['text'] = $notice->content; 		
		$twitter_status['truncated'] = false; # Not possible on Laconica
		$twitter_status['created_at'] = $this->date_twitter($notice->created);
		$twitter_status['in_reply_to_status_id'] = ($notice->reply_to) ? intval($notice->reply_to) : NULL;
		$twitter_status['source'] = NULL; # XXX: twitterific, twitterfox, etc. Not supported yet.
		$twitter_status['id'] = intval($notice->id);
		$twitter_status['in_reply_to_user_id'] = ($notice->reply_to) ? $this->replier_by_reply($notice->reply_to) : NULL;
		$twitter_status['favorited'] = NULL; # XXX: Not implemented on Laconica yet.
		
		$profile = $notice->getProfile();
		$twitter_user = $this->twitter_user_array($profile);
		$twitter_status['user'] = $twitter_user;
				
		return $twitter_status;
	}
	
	function render_twitter_xml_status($twitter_status) {	
		common_element_start('status');
		common_element('created_at', NULL, $twitter_status['created_at']);
		common_element('id', NULL, $twitter_status['id']);
		common_element('text', NULL, $twitter_status['text']);
		common_element('source', NULL, $twitter_status['source']);  
		common_element('truncated', NULL, $twitter_status['truncated']); 
		common_element('in_reply_to_status_id', NULL, $twitter_status['in_reply_to_status_id']);
		common_element('in_reply_to_user_id', NULL, $twitter_status['in_reply_to_user_id']);
		common_element('favorited', Null, $twitter_status['favorited']);  

		$this->render_twitter_xml_user($twitter_status['user']);
		
		common_element_end('status');
	}	
	
	function render_twitter_xml_user($twitter_user) {
		common_element_start('user');
		common_element('id', NULL, $twitter_user['id']);
		common_element('name', NULL, $twitter_user['name']);
		common_element('screen_name', NULL, $twitter_user['screen_name']);
		common_element('location', NULL, $twitter_user['location']);
		common_element('description', NULL, $twitter_user['description']);		
		common_element('profile_image_url', NULL, $twitter_user['profile_image_url']);
		common_element('url', NULL, $twitter_user['url']);
		common_element('protected', NULL, $twitter_user['protected']);
		common_element('followers_count', NULL, $twitter_user['followers_count']);
		common_element_end('user');
	}
	
	
	function render_twitter_json_statuses($twitter_statuses) {
		print(json_encode($twitter_statuses));
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
			common_debug("TwitterapiAction::replier_by_reply: Got a bad notice_id: $reply_id");
		}

		$profile = $notice->getProfile();
		
		if (!$profile) {
			common_debug("TwitterapiAction::replier_by_reply: Got a bad profile_id: $profile_id");
			return false;
		}
		
		return intval($profile->id);		
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
		
		if ($count > 0) {
			return $count;
		}
		
		return NULL;
	}
	
	function init_twitter_rss() {
		
		common_start_xml();
		common_element_start('rss', array('version' => '2.0'));
	}
	
	function end_twitter_rss() {
		
		common_element_end('rss');
		common_end_xml();

	}

	function get_twitter_channel() {
		
	}

/*

  <item>
    <title>CapitalD: @baxterd Have you read The Conquest of Cool? It looks interesting to me...</title>
    <description>CapitalD: @baxterd Have you read The Conquest of Cool? It looks interesting to me...</description>
    <pubDate>Mon, 14 Jul 2008 23:54:13 +0000</pubDate>
    <guid>http://twitter.com/CapitalD/statuses/858499551</guid>
    <link>http://twitter.com/CapitalD/statuses/858499551</link>
  </item>

*/

	function show_twitter_rss_item($twitter_status) {
		common_element_start('item');
		common_element('title', NULL, "{$twitter_status[user][screen_name]}: $twitter_status[text]");
		common_element('description', NULL, "{$twitter_status[user][screen_name]}: $twitter_status[text]");
		common_element('pubDate', NULL, "$twitter_status[created_at]");
		common_element('guid', NULL, "$twitter_status[id]");
		common_element('link', NULL, "$twitter_status[id]");
		common_element_end('item');
	}

	
}