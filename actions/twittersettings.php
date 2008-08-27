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
		return _('Add your Twitter account credentials to automatically send your notices to Twitter, ' .
			'and subscribe to Twitter friends already here.');
	}

	function show_form($msg=NULL, $success=false) {
		
		$user = common_current_user();
		$profile = $user->getProfile();
		$fuser = Foreign_user::getForeignUser($user->id, 0);
		
		$this->form_header(_('Twitter settings'), $msg, $success);

		common_element_start('form', array('method' => 'post',
										   'id' => 'twittersettings',
										   'action' =>
										   common_local_url('twittersettings')));

		if ($fuser) {

			common_element_start('p');
			
			common_element('span', 'Twitter User', "http://www.twitter.com/$fuser->nickname");
			common_element('span', 'input_instructions',
			               _('Current verified Twitter User'));
			common_hidden('fuser_id', $fuser->id);
			
			common_element_end('p');
			common_submit('remove', _('Remove'));
			
		} else {

			// XXX: Should we make an educated guess as to the twitter accnt name? -- Zach
			common_input('twitter_username', _('Twitter Username'),
						 ($this->arg('twitter_username')) ? $this->arg('twitter_username') : $profile->nickname,
						 _('No spaces, please.')); // hey, it's what Twitter says

			common_password('twitter_password', _('Twitter Password'));		
			
			common_submit('add', _('Add'));
				
		}
	
		common_element('h2', NULL, _('Preferences'));
		
		// XXX: these checkboxes don't do anything yet
		common_checkbox('repost', _('Automatically send my notices to Twitter.'), true);
		common_checkbox('subscribe_friends', _('Subscribe to my Twitter friends here.'), true);

		common_submit('save', _('Save'));
		
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {
		
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

		$user = common_current_user();		
		$fuser = Foreign_user::getForeignUser($user->id, 0);


		$twitter_username = $this->trimmed('twitter_username');
		$twitter_password = $this->trimmed('twitter_password');
		
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
			
		$user = common_current_user();
				
		$fuser = Foreign_user::save(
				array(
					'id' => $twitter_id,
					'service' => '0', // Twitter
					'uri' => "http://www.twitter.com/$twitter_username",
					'nickname' => $twitter_username, 
					'user_id' => $user->id,
					'credentials' => $twitter_password
				));
		
		if (!$fuser) {
			$this->show_form(_('Unable to save your Twitter credentials!'));
		}
		
		$this->show_form(_('Twitter settings saved.'), true);
	}

	function remove_twitter_acct() {
		
		$user = common_current_user();
		$fuser = Foreign_user::getForeignUser($user->id, 0);
	
		$fuser_id = $this->arg('fuser_id');

		# Maybe an old tab open...?

		if ($fuser->id != $fuser_id) {
		    $this->show_form(_('That is not your Twitter account.'));
		    return;
		}

		$result = $fuser->delete();
		
		if (!$result) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t remove Twitter user.'));
			return;
		}

		$this->show_form(_('Twitter account removed.'), TRUE);
	}
	
	function save_preferences() {

		$user = common_current_user();		
		$fuser = Foreign_user::getForeignUser($user->id, 0);
		
		$this->show_form(_('Save doesn\'t do anything yet.'));
		
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