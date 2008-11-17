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

require_once(INSTALLDIR.'/lib/settingsaction.php');

define('SUBSCRIPTIONS', 80);

class TwittersettingsAction extends SettingsAction {

	var $twit_id;
	var $twit_username;
	var $twit_password;
	var $friends_count = 0;
	var $noticesync;
	var $repliessync;
	var $friendsync;

	function get_instructions() {
		return _('Add your Twitter account to automatically send your notices to Twitter, ' .
			'and subscribe to Twitter friends already here.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$profile = $user->getProfile();
		$fuser = NULL;
		$flink = Foreign_link::getByUserID($user->id, 1); // 1 == Twitter

		if ($flink) {
			$fuser = $flink->getForeignUser();
		}

		$this->form_header(_('Twitter settings'), $msg, $success);
		common_element_start('form', array('method' => 'post',
										   'id' => 'twittersettings',
										   'action' =>
										   common_local_url('twittersettings')));
		common_hidden('token', common_session_token());

		common_element('h2', NULL, _('Twitter Account'));

		if ($fuser) {
			common_element_start('p');

			common_element('span', 'twitter_user', $fuser->nickname);
			common_element('a', array('href' => $fuser->uri),  $fuser->uri);
			common_element('span', 'input_instructions',
			               _('Current verified Twitter account.'));
			common_hidden('flink_foreign_id', $flink->foreign_id);
			common_element_end('p');
			common_submit('remove', _('Remove'));
		} else {
			common_input('twitter_username', _('Twitter Username'),
						 ($this->arg('twitter_username')) ? $this->arg('twitter_username') : $profile->nickname,
						 _('No spaces, please.')); // hey, it's what Twitter says

			common_password('twitter_password', _('Twitter Password'));
		}

		common_element('h2', NULL, _('Preferences'));

		common_checkbox('noticesync', _('Automatically send my notices to Twitter.'),
						($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND) : true);

		common_checkbox('replysync', _('Send local "@" replies to Twitter.'),
						($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true);

		common_checkbox('friendsync', _('Subscribe to my Twitter friends here.'),
						($flink) ? ($flink->friendsync & FOREIGN_FRIEND_RECV) : true);

		if ($flink) {
			common_submit('save', _('Save'));
		} else {
			common_submit('add', _('Add'));
		}

		
		$this->show_twitter_subscriptions();

		common_element_end('form');
		
		common_show_footer();
	}
	
	function subscribed_twitter_users() {

		$current_user = common_current_user();
		
		$qry = 'SELECT user.* ' .
			'FROM subscription ' . 
			'JOIN user ON subscription.subscribed = user.id ' .
			'JOIN foreign_link ON foreign_link.user_id = user.id ' . 
			'WHERE subscriber = %d ' . 
			'ORDER BY user.nickname';

		$user = new User();
		
		$user->query(sprintf($qry, $current_user->id));

		$users = array();

		while ($user->fetch()) {
			$users[] = clone($user);
		}
		
		return $users;
	}
	
	
	function show_twitter_subscriptions() {
	
		common_debug('show twitter subs');
		$friends = $this->subscribed_twitter_users();

		$friends_count = count($friends);

		common_debug("friends count = $friends_count");

		if ($friends_count > 0) {

			common_element('h3', NULL, _('Twitter Friends'));
			common_element_start('div', array('id' => 'subscriptions'));
			common_element_start('ul', array('id' => 'subscriptions_avatars'));

			for ($i = 0; $i < min($friends_count, SUBSCRIPTIONS); $i++) {

				$other = Profile::staticGet($friends[$i]->id);

				if (!$other) {
					common_log_db_error($subs, 'SELECT', __FILE__);
					continue;
				}
				
				common_element_start('li');
				common_element_start('a', array('title' => ($other->fullname) ?
												$other->fullname :
												$other->nickname,
												'href' => $other->profileurl,
												'rel' => 'contact',
												'class' => 'subscription'));
				$avatar = $other->getAvatar(AVATAR_MINI_SIZE);
				common_element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
											'width' => AVATAR_MINI_SIZE,
											'height' => AVATAR_MINI_SIZE,
											'class' => 'avatar mini',
											'alt' =>  ($other->fullname) ?
											$other->fullname :
											$other->nickname));
				common_element_end('a');
				common_element_end('li');
		
			}

			common_element_end('ul');
			common_element_end('div');

		}

		// XXX Figure out a way to show all Twitter friends...
		
		/*
		if ($subs_count > SUBSCRIPTIONS) {
			common_element_start('p', array('id' => 'subscriptions_viewall'));

			common_element('a', array('href' => common_local_url('subscriptions',
																 array('nickname' => $profile->nickname)),
									  'class' => 'moresubscriptions'),
						   _('All subscriptions'));
			common_element_end('p');
		}
		*/

	}
	
	

	function handle_post() {

		# CSRF protection
		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->show_form(_('There was a problem with your session token. Try again, please.'));
			return;
		}

		if ($this->arg('save')) {
			$this->save_preferences();
		} else if ($this->arg('add')) {
			$this->add_twitter_acct();
		} else if ($this->arg('remove')) {
			$this->remove_twitter_acct();
		} else {
			$this->show_form(_('Unexpected form submission.'));
		}
	}

	function add_twitter_acct() {

		$this->twit_username = $this->trimmed('twitter_username');
		$this->twit_password = $this->trimmed('twitter_password');
		$this->noticesync = $this->boolean('noticesync');
		$this->replysync = $this->boolean('replysync');
		$this->friendsync = $this->boolean('friendsync');

		if (!Validate::string($this->twit_username, array('min_length' => 1,
													   'max_length' => 15,
													   'format' => VALIDATE_NUM . VALIDATE_ALPHA . '_'))) {
			$this->show_form(_('Username must have only numbers, upper- and lowercase letters, and underscore (_). 15 chars max.'));
			return;
		}

		// Verify this is a real Twitter user.
		if (!$this->verify_credentials()) {
			$this->show_form(_('Could not verify your Twitter credentials!'));
			return;
		}

		if (!$this->twitter_user_info()) {
			$this->show_form(sprintf(_('Unable to retrieve account information for "%s" from Twitter.'),
				$twitter_username));
			return;
		}

		$fuser_id = $this->update_twitter_user($this->twit_id, $this->twit_username);

		if (!$fuser_id) {
			$this->show_form(_('Unable to save your Twitter settings!'));
			return;
		}

		$user = common_current_user();

		$flink = DB_DataObject::factory('foreign_link');
		$flink->user_id = $user->id;
		$flink->foreign_id = $fuser_id;
		$flink->service = 1; // Twitter
		$flink->credentials = $this->twit_password;
		$flink->created = common_sql_now();

		$this->set_flags($flink, $this->noticesync, $this->replysync, $this->friendsync);

		$flink_id = $flink->insert();

		if (!$flink_id) {
			common_log_db_error($flink, 'INSERT', __FILE__);
			$this->show_form(_('Unable to save your Twitter settings!'));
			return;
		}

		if ($this->friendsync) {
			$this->save_friends();
		}

		$this->show_form(_('Twitter settings saved.'), true);
	}

	function remove_twitter_acct() {
		$user = common_current_user();

		// For now we assume one Twitter acct per Laconica acct
		$flink = Foreign_link::getByUserID($user->id, 1);
		$flink_foreign_id = $this->arg('flink_foreign_id');

		if (!$flink) {
			common_debug("couldn't get flink");
		}

		# Maybe an old tab open...?
		if ($flink->foreign_id != $flink_foreign_id) {
			common_debug("flink user_id = " . $flink->user_id);
		    $this->show_form(_('That is not your Twitter account.'));
		    return;
		}

		$result = $flink->delete();

		if (!$result) {
			common_log_db_error($flink, 'DELETE', __FILE__);
			common_server_error(_('Couldn\'t remove Twitter user.'));
			return;
		}

		$this->show_form(_('Twitter account removed.'), TRUE);
	}

	function save_preferences() {
		$this->noticesync = $this->boolean('noticesync');
		$this->friendsync = $this->boolean('friendsync');
		$this->replysync = $this->boolean('replysync');

		$user = common_current_user();
		$flink = Foreign_link::getByUserID($user->id, 1);

		if (!$flink) {
			common_log_db_error($flink, 'SELECT', __FILE__);
			$this->show_form(_('Couldn\'t save Twitter preferences.'));
			return;
		}

		$this->twit_id = $flink->foreign_id;
		$this->twit_password = $flink->credentials;

		$fuser = $flink->getForeignUser();

		if (!$fuser) {
			common_log_db_error($fuser, 'SELECT', __FILE__);
			$this->show_form(_('Couldn\'t save Twitter preferences.'));
			return;
		}

		$this->twit_username = $fuser->nickname;

		$original = clone($flink);
		$this->set_flags($flink, $this->noticesync, $this->replysync, $this->friendsync);
		$result = $flink->update($original);

		if ($result === FALSE) {
			common_log_db_error($flink, 'UPDATE', __FILE__);
			$this->show_form(_('Couldn\'t save Twitter preferences.'));
			return;
		}

		if ($this->friendsync) {
			$this->save_friends();
		}

		$this->show_form(_('Twitter preferences saved.'));
	}

	function twitter_user_info() {
		$uri = "http://twitter.com/users/show/$this->twit_username.json";
		$data = $this->get_twitter_data($uri);

		if (!$data) {
			return false;
		}

		$twit_user = json_decode($data);

		if (!$twit_user) {
			return false;
		}

		$this->friends_count = $twit_user->friends_count;
		$this->twit_id = $twit_user->id;

		common_debug("Twitter_id = $this->twit_id");
		common_debug("Friends_count = $this->friends_count");

		return true;
	}

	function verify_credentials() {
		$uri = 'http://twitter.com/account/verify_credentials.json';
		$data = $this->get_twitter_data($uri);

		if (!$data) {
			return false;
		}

		$creds = json_decode($data);

		if (!$creds) {
			return false;
		}

		if ($creds->authorized == 1) {
			return true;
		}

		return false;
	}

	function get_twitter_data($uri) {

		$options = array(
				CURLOPT_USERPWD => sprintf("%s:%s", $this->twit_username, $this->twit_password),
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_FAILONERROR		=> true,
				CURLOPT_HEADER			=> false,
				CURLOPT_FOLLOWLOCATION	=> true,
				// CURLOPT_USERAGENT		=> "identi.ca",
				CURLOPT_CONNECTTIMEOUT	=> 120,
				CURLOPT_TIMEOUT			=> 120
		);


		$ch = curl_init($uri);
	    curl_setopt_array($ch, $options);
	    $data = curl_exec($ch);
	    $errmsg = curl_error($ch);

		if ($errmsg) {
			common_debug("cURL error: $errmsg - trying to load: $uri with user $this->twit_user.",
				__FILE__);
		}

		curl_close($ch);

		return $data;
	}

	function set_flags(&$flink, $noticesync, $replysync, $friendsync) {
		if ($noticesync) {
			$flink->noticesync |= FOREIGN_NOTICE_SEND;
		} else {
			$flink->noticesync &= ~FOREIGN_NOTICE_SEND;
		}

		if ($replysync) {
			$flink->noticesync |= FOREIGN_NOTICE_SEND_REPLY;
		} else {
			$flink->noticesync &= ~FOREIGN_NOTICE_SEND_REPLY;
		}

		if ($friendsync) {
			$flink->friendsync |= FOREIGN_FRIEND_RECV;
		} else {
			$flink->friendsync &= ~FOREIGN_FRIEND_RECV;
		}

		$flink->profilesync = 0; // XXX: leave as default?
	}

	function save_friends() {

		$uri = 'http://twitter.com/statuses/friends.json?page=';

		$this->twitter_user_info();

		// Calculate how many pages to get...
		$pages = ceil($this->friends_count / 100);

		common_debug("number of pages to get: $pages");

		$friends = array();

		for ($i = 1; $i <= $pages; $i++) {

			$data = $this->get_twitter_data($uri . $i);

			common_debug("fetching " . $uri . $i);

			if (!$data) {
				return false;
			}

			common_debug("got data");
		
			$more_friends = json_decode($data);
			
			if (!$more_friends) {
				return false;
			}

	 		$friends = array_merge($friends, $more_friends);

		}
		
		common_debug("number of friends =" + count($friends));

		$user = common_current_user();

	    foreach ($friends as $friend) {
		
			$friend_name = $friend->screen_name;
			$friend_id = $friend->id;

			// Update or create the Foreign_user record
			$this->update_twitter_user($friend_id, $friend_name);
			
			// Check to see if there's a related local user
			$flink = Foreign_link::getByForeignID($friend_id, 1);
						
			if ($flink) {
				
				// Get associated user
				$friend_user = User::staticGet('id', $flink->user_id);				
				subs_subscribe_to($user, $friend_user);
				
			}
		}
		
	}

	// Creates or Updates a Twitter user
	function update_twitter_user($twitter_id, $screen_name) {

		$fuser = null;

		$uri = "http://twitter.com/$screen_name";

		// Check to see whether the Twitter user is already in the system,
		// and update its screen name and uri if so.
		$fuser = Foreign_User::getForeignUser($twitter_id, 1);

		if ($fuser) {

			// Only update if Twitter screen name has changed
			if ($fuser->nickname != $screen_name) {

				$original = clone($fuser);
				$fuser->nickname = $screen_name;
				$fuser->uri = $uri;
				$result = $fuser->updateKeys($original);

				if (!$result) {
					common_log_db_error($fuser, 'UPDATE', __FILE__);
					return null;
				}

				common_debug(
					sprintf('Updated Twitter user %, screen name was: %, now: %s.',
						$twitter_id, $original->nickname, $screen_name));
			}

			common_debug("No update for $screen_name needed.");

		} else {

				// Otherwise, create a new Twitter user
				$fuser = DB_DataObject::factory('foreign_user');

				$fuser->nickname = $screen_name;
				$fuser->uri = $uri;
				$fuser->id = $twitter_id;
				$fuser->service = 1; // Twitter
				$fuser->created = common_sql_now();
				$result = $fuser->insert();

				if (!$result) {
					common_debug("Failed to add new Twitter user: $twitter_id - $screen_name.");
					common_log_db_error($fuser, 'INSERT', __FILE__);
					return null;
				}

				common_debug("Added new Twitter user: $twitter_id - $screen_name.");

				//	common_debug(print_r($friend, true));
		}

		return $fuser->id;

	}

}