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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	 If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapiusersAction extends TwitterapiAction {

	function is_readonly() {			
		return true;
	}

/*	
	Returns extended information of a given user, specified by ID or
	screen name as per the required id parameter below.	 This information
	includes design settings, so third party developers can theme their
	widgets according to a given user's preferences. You must be properly
	authenticated to request the page of a protected user.

	URL: http://twitter.com/users/show/id.format

	Formats: xml, json

	Parameters:

	* id.  Required.  The ID or screen name of a user.
	Ex: http://twitter.com/users/show/12345.json or
	http://twitter.com/users/show/bob.xml

	* email. Optional.	The email address of a user.  Ex:
	http://twitter.com/users/show.xml?email=test@example.com

*/	
	function show($args, $apidata) {
		parent::handle($args);
		
		$user = null;
		$email = $this->arg('email');
		
		if (isset($apidata['api_arg'])) {
			if (is_numeric($apidata['api_arg'])) {
				// by user id
				$user = User::staticGet($apidata['api_arg']);			
			} else {
				// by nickname
				$nickname = common_canonical_nickname($apidata['api_arg']);
				$user = User::staticGet('nickname', $nickname);
			} 
		} elseif ($email) {
			// or, find user by email address
			// XXX: The Twitter API spec say an id is *required*, but you can actually
			// pull up a user with just an email address. -- Zach
			$user = User::staticGet('email', $email);			
		} 

		if (!$user) {
			// XXX: Twitter returns a random(?) user instead of throwing and err! -- Zach
			$this->client_error(_('User not found.'), 404, $apidata['content-type']);
			exit();
		}
		
		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile.'));
			exit();
		}

		$twitter_user = $this->twitter_user_array($profile, true);

		// Add in extended user fields offered up by this method
		$twitter_user['created_at'] = $this->date_twitter($profile->created);

		$subbed = DB_DataObject::factory('subscription');
		$subbed->subscriber = $profile->id;
		$subbed_count = (int) $subbed->count() - 1;

		$notices = DB_DataObject::factory('notice');
		$notices->profile_id = $profile->id;
		$notice_count = (int) $notices->count();

		$twitter_user['friends_count'] = (is_int($subbed_count)) ? $subbed_count : 0;
		$twitter_user['statuses_count'] = (is_int($notice_count)) ? $notice_count : 0;

		// Other fields Twitter sends...
		$twitter_user['profile_background_color'] = '';
		$twitter_user['profile_text_color'] = '';
		$twitter_user['profile_link_color'] = '';
		$twitter_user['profile_sidebar_fill_color'] = '';
		$twitter_user['favourites_count'] = 0;
		$twitter_user['utc_offset'] = '';
		$twitter_user['time_zone'] = '';
		$twitter_user['following'] = '';
		$twitter_user['notifications'] = '';

		if ($apidata['content-type'] == 'xml') { 
			$this->init_document('xml');
			$this->show_twitter_xml_user($twitter_user);
			$this->end_document('xml');
		} elseif ($apidata['content-type'] == 'json') {
			$this->init_document('json');
			$this->show_twitter_json_users($twitter_user);
			$this->end_document('json');
		} else {
			common_user_error(_('API method not found!'), $code = 404);
		}
			
		exit();
	}
}
