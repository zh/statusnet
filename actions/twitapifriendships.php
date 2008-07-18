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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapifriendshipsAction extends TwitterapiAction {

	function create($args, $apidata) {
		parent::handle($args);

		$id = $apidata['api_arg'];

		$other = $this->get_user($id);

		if (!$other) {
			$this->client_error(_('No such user'));
			exit();
			return;
		}
		
		$user = $apidata['user'];
		
		if ($user->isSubscribed($other)) {
			$this->client_error(_('Already subscribed.'));
			exit();
			return;
		}
		
		$sub = new Subscription();
		
		$sub->query('BEGIN');
		
		$sub->subscriber = $user->id;
		$sub->subscribed = $other->id;
		$sub->created = DB_DataObject_Cast::dateTime(); # current time
		  
		$result = $sub->insert();

		if (!$result) {
			$this->server_error(_('Could not subscribe'));
			exit();
			return;
		}
		
		$sub->query('COMMIT');
		
		mail_subscribe_notify($other, $user);

		$type = $apidata['content-type'];
		$this->init_document($type);
		$this->show_profile($other);
		$this->end_document($type);
		exit();
	}
	
	//destroy
	//
	//Discontinues friendship with the user specified in the ID parameter as the authenticating user.  Returns the un-friended user in the requested format when successful.  Returns a string describing the failure condition when unsuccessful. 
	//
	//URL: http://twitter.com/friendships/destroy/id.format
	//
	//Formats: xml, json
	//
	//Parameters:
	//
	//* id.  Required.  The ID or screen name of the user with whom to discontinue friendship.  Ex: http://twitter.com/friendships/destroy/12345.json or http://twitter.com/friendships/destroy/bob.xml
	
	function destroy($args, $apidata) {
		parent::handle($args);
		$id = $apidata['api_arg'];

		# We can't subscribe to a remote person, but we can unsub
		
		$other = $this->get_profile($id);
		$user = $apidata['user'];
		
		$sub = new Subscription();
		$sub->subscriber = $user->id;
		$sub->subscribed = $other->id;
		
		if ($sub->fetch(TRUE)) {
			$sub->query('BEGIN');
			$sub->delete();
			$sub->query('COMMIT');
		} else {
			$this->client_error(_('Not subscribed'));
			exit();
		}

		$type = $apidata['content-type'];
		$this->init_document($type);
		$this->show_profile($other);
		$this->end_document($type);
		exit();
	}

	//	Tests if a friendship exists between two users.
	//	  
	//	  
	//	  URL: http://twitter.com/friendships/exists.format
	//	
	//	Formats: xml, json, none
	//	  
	//	  Parameters:
	//	
	//	    * user_a.  Required.  The ID or screen_name of the first user to test friendship for.
	//	      * user_b.  Required.  The ID or screen_name of the second user to test friendship for.
	//	  * Ex: http://twitter.com/friendships/exists.xml?user_a=alice&user_b=bob
	
	function exists($args, $apidata) {
		parent::handle($args);
		$user_a_id = $this->trimmed('user_a');
		$user_b_id = $this->trimmed('user_b');
		$user_a = $this->get_profile($user_a_id);
		$user_b = $this->get_profile($user_b_id);
		
		if (!$user_a || !$user_b) {
			$this->client_error(_('No such user'));
			return;
		}
		
		if ($user_a->isSubscribed($user_b)) {
			$result = 'true';
		} else {
			$result = 'false';
		}
		
		switch ($apidata['content-type']) {
		 case 'xml':
			common_start_xml();
			common_element('friends', NULL, $result);
			common_end_xml();
			break;
		 case 'json':
			print json_encode($result);
			print "\n";
			break;
		 default:
			print $result;
			break;
		}
		
	}

	function get_profile($id) {
		if (is_numeric($id)) {
			return Profile::staticGet($id);
		} else {
			$user = User::staticGet('nickname', $id);
			if ($user) {
				return $user->getProfile();
			} else {
				return NULL;
			}
		}
	}
	
	function get_user($id) {
		if (is_numeric($id)) {
			return User::staticGet($id);
		} else {
			return User::staticGet('nickname', $id);
		}
	}
}