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

class TwittersettingsAction extends SettingsAction {

	function get_instructions() {
		return _('Add your Twitter account to automatically send your notices to Twitter, ' .
			'and subscribe to Twitter friends already here.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$profile = $user->getProfile();
		$fuser = NULL;
		$flink = Foreign_link::getForeignLink($user->id, 1); // 1 == Twitter

		if ($flink) {
			$fuser = Foreign_user::staticGet('user_id', $flink->user_id);
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
			common_hidden('flink_user_id', $flink->user_id);
			common_element_end('p');
			common_submit('remove', _('Remove'));
		} else {

			// XXX: Should we make an educated guess as to the twitter acct name? -- Zach
			common_input('twitter_username', _('Twitter Username'),
						 ($this->arg('twitter_username')) ? $this->arg('twitter_username') : $profile->nickname,
						 _('No spaces, please.')); // hey, it's what Twitter says

			common_password('twitter_password', _('Twitter Password'));
		}

		common_element('h2', NULL, _('Preferences'));

		if ($flink) {
			common_checkbox('noticesync', _('Automatically send my notices to Twitter.'),
				($flink->noticesync) ? true : false);
			common_checkbox('friendsync', _('Subscribe to my Twitter friends here.'),
				($flink->friendsync) ? true : false);
			common_submit('save', _('Save'));
		} else {
			common_checkbox('noticesync', _('Automatically send my notices to Twitter.'), true);
			common_checkbox('friendsync', _('Subscribe to my Twitter friends here.'), true);
			common_submit('add', _('Add'));
		}

		common_element_end('form');
		common_show_footer();
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
		$twitter_username = $this->trimmed('twitter_username');
		$twitter_password = $this->trimmed('twitter_password');
		$noticesync = $this->boolean('noticesync');
		$friendsync = $this->boolean('friendsync');

		if (!Validate::string($twitter_username, array('min_length' => 1,
											   'max_length' => 64,
											   'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->show_form(_('Username must have only lowercase letters and numbers and no spaces.'));
			return;
		}

		// Verify this is a real Twitter user.
		if (!$this->verify_credentials($twitter_username, $twitter_password)) {
			$this->show_form(_('Could not verify your Twitter credentials!'));
			return;
		}

		// Now that we have a valid Twitter user, we have to make another api call to
		// find its Twitter ID.  Dumb, but true.
		$twitter_id = $this->get_twitter_id($twitter_username);

		if (!$twitter_id) {
			$this->show_form(sprintf(_('Unable to retrieve account information for "%s" from Twitter.'), $twitter_username));
			return;
		}

		$fuser = DB_DataObject::factory('foreign_user');
		$fuser->id = $twitter_id;
		$fuser->service = 1; // Twitter
		$fuser->uri = "http://www.twitter.com/$twitter_username";
		$fuser->nickname = $twitter_username;
		$fuser->created = common_sql_now();
		$result = $fuser->insert();

		if (!$result) {
			common_log_db_error($fuser, 'INSERT', __FILE__);
			$this->show_form(_('Unable to save your Twitter settings!'));
			return;
		}

		$user = common_current_user();

		$flink = DB_DataObject::factory('foreign_link');
		$flink->user_id = $user->id;
		$flink->foreign_id = $fuser->id;
		$flink->service = 1; // Twitter
		$flink->credentials = $twitter_password;
		$flink->created = common_sql_now();
		$flink->noticesync = ($noticesync) ? 1 : 0;
		$flink->friendsync = ($friendsync) ? 2 : 0;
		$flink->profilesync = 0; // XXX: leave as default?
		$flink_id = $flink->insert();

		if (!$flink_id) {
			common_log_db_error($flink, 'INSERT', __FILE__);
			$this->show_form(_('Unable to save your Twitter settings!'));
			return;
		}

		$this->show_form(_('Twitter settings saved.'), true);
	}

	function remove_twitter_acct() {
		$user = common_current_user();

		// For now we assume one Twitter acct per Laconica acct
		$flink = Foreign_link::getForeignLink($user->id, 1);
		$fuser = Foreign_user::getForeignUser($flink->foreign_id, 1);
		$flink_user_id = $this->arg('flink_user_id');

		if (!$flink) {
			common_debug("couldn't get flink");
		}

		# Maybe an old tab open...?
		if ($flink->user_id != $flink_user_id) {
			common_debug("flink user_id = " . $flink->user_id);
		    $this->show_form(_('That is not your Twitter account.'));
		    return;
		}

		$result = $fuser->delete();

		if (!$result) {
			common_log_db_error($flink, 'DELETE', __FILE__);
			$this->show_form(_('Couldn\'t remove Twitter user.'));
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
		$noticesync = $this->boolean('noticesync');
		$friendsync = $this->boolean('friendsync');
		$user = common_current_user();
		$flink = Foreign_link::getForeignLink($user->id, 1);

		if (!$flink) {
			common_log_db_error($flink, 'SELECT', __FILE__);
			$this->show_form(_('Couldn\'t save Twitter preferences.'));
			return;
		}

		$flink->noticesync = ($noticesync) ? 1 : 0;
		$flink->friendsync = ($friendsync) ? 2 : 0;
		// $flink->profilesync = 0; // XXX: leave as default?
		$result = $flink->update();

		if (!$result) {
			common_log_db_error($flink, 'UPDATE', __FILE__);
			$this->show_form(_('Couldn\'t save Twitter preferences.'));
			return;
		}

		$this->show_form(_('Twitter preferences saved.'));

		return;
	}

	function get_twitter_id($twitter_username) {
		$uri = "http://twitter.com/users/show/$twitter_username.json";
		$data = $this->get_twitter_data($uri);

		if (!$data) {
			return NULL;
		}

		$user = json_decode($data);

		if (!$user) {
			return NULL;
		}

		return $user->id;
	}

	function verify_credentials($user, $password) {
		$uri = 'http://twitter.com/account/verify_credentials.json';
		$data = $this->get_twitter_data($uri, $user, $password);

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

	// PHP's cURL the best thing to use here? -- Zach
	function get_twitter_data($uri, $user=NULL, $password=NULL) {
		$options = array(
				CURLOPT_USERPWD => "$user:$password",
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
			common_debug("cURL error: $errmsg - trying to load: $uri with user $user.", __FILE__);
		}

		curl_close($ch);

		return $data;
	}

}