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

class ProfilesettingsAction extends SettingsAction {

	function show_top($arr) {
		$msg = $arr[0];
		$success = $arr[1];
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('You can update your personal profile info here '.
							  'so people know more about you.'));
		}
		$this->settings_menu();
	}
	
	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$profile = $user->getProfile();
		common_show_header(_t('Profile settings'), NULL, array($msg, $success),
						   array($this, 'show_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'profilesettings',
										   'action' =>
										   common_local_url('profilesettings')));
		# too much common patterns here... abstractable?
		common_input('nickname', _t('Nickname'),
					 ($this->arg('nickname')) ? $this->arg('nickname') : $profile->nickname,
					 _t('1-64 lowercase letters or numbers, no punctuation or spaces'));
		common_input('fullname', _t('Full name'),
					 ($this->arg('fullname')) ? $this->arg('fullname') : $profile->fullname);
		common_input('email', _t('Email address'),
					 ($this->arg('email')) ? $this->arg('email') : $user->email,
					 _t('Used only for updates, announcements, and password recovery'));
		common_input('homepage', _t('Homepage'),
					 ($this->arg('homepage')) ? $this->arg('homepage') : $profile->homepage,
					 _t('URL of your homepage, blog, or profile on another site'));
		common_textarea('bio', _t('Bio'),
						($this->arg('bio')) ? $this->arg('bio') : $profile->bio,
						_t('Describe yourself and your interests in 140 chars'));
		common_input('location', _t('Location'),
					 ($this->arg('location')) ? $this->arg('location') : $profile->location,
					 _t('Where you are, like "City, State (or Region), Country"'));
		common_submit('submit', _t('Save'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {
		
		$nickname = $this->trimmed('nickname');
		$fullname = $this->trimmed('fullname');
		$email = $this->trimmed('email');
		$homepage = $this->trimmed('homepage');
		$bio = $this->trimmed('bio');
		$location = $this->trimmed('location');

		# Some validation
		
		if ($email && !Validate::email($email, true)) {
			$this->show_form(_t('Not a valid email address.'));
			return;
		} else if (!Validate::string($nickname, array('min_length' => 1,
													  'max_length' => 64,
													  'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->show_form(_t('Nickname must have only letters and numbers and no spaces.'));
			return;
		} else if (!User::allowed_nickname($nickname)) {
			$this->show_form(_t('Not a valid nickname.'));
			return;
		} else if (!is_null($homepage) && (strlen($homepage) > 0) &&
				   !Validate::uri($homepage, array('allowed_schemes' => array('http', 'https')))) {
			$this->show_form(_t('Homepage is not a valid URL.'));
			return;
		} else if (!is_null($fullname) && strlen($fullname) > 255) {
			$this->show_form(_t('Fullname is too long (max 255 chars).'));
			return;
		} else if (!is_null($bio) && strlen($bio) > 140) {
			$this->show_form(_t('Bio is too long (max 140 chars).'));
			return;
		} else if (!is_null($location) && strlen($location) > 255) {
			$this->show_form(_t('Location is too long (max 255 chars).'));
			return;
		} else if ($this->nickname_exists($nickname)) {
			$this->show_form(_t('Nickname already exists.'));
			return;
		} else if ($this->email_exists($email)) {
			$this->show_form(_t('Email address already exists.'));
			return;
		}
		
		$user = common_current_user();

		$user->query('BEGIN');

		if ($user->nickname != $nickname) {
			
			common_debug('Updating user nickname from ' . $user->nickname . ' to ' . $nickname,
						 __FILE__);
			
			$original = clone($user);
		
			$user->nickname = $nickname;

			$result = $user->updateKeys($original);
		
			if ($result === FALSE) {
				common_log_db_error($user, 'UPDATE', __FILE__);
				common_server_error(_t('Couldnt update user.'));
				return;
			}
		}

		if ($user->email != $email) {
			
			common_debug('Updating user email from ' . $user->email . ' to ' . $email,
						 __FILE__);
			
			# We don't update email directly; it gets done by confirmemail

			$confirm = new Confirm_address();
			
			$confirm->code = common_confirmation_code(128);
			$confirm->user_id = $user->id;
			$confirm->address = $email;
			$confirm->address_type = 'email';
			
			$result = $confirm->insert();
			
			if (!$result) {
				common_log_db_error($confirm, 'INSERT', __FILE__);
				common_server_error(_t('Couldnt confirm email.'));
				return FALSE;
			}
			
			# XXX: try not to do this in the middle of a transaction
		
			mail_confirm_address($confirm->code,
								 $profile->nickname,
								 $email);
		}
		
		$profile = $user->getProfile();

		$orig_profile = clone($profile);

		$profile->nickname = $user->nickname;
		$profile->fullname = $fullname;
		$profile->homepage = $homepage;
		$profile->bio = $bio;
		$profile->location = $location;
		$profile->profileurl = common_profile_url($nickname);

		common_debug('Old profile: ' . common_log_objstring($orig_profile), __FILE__);
		common_debug('New profile: ' . common_log_objstring($profile), __FILE__);
		
		$result = $profile->update($orig_profile);
		
		if (!$result) {
			common_log_db_error($profile, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt save profile.'));
			return;
		}

		$user->query('COMMIT');

		common_broadcast_profile($profile);

		$this->show_form(_t('Settings saved.'), TRUE);
	}
	
	function nickname_exists($nickname) {
		$user = common_current_user();
		$other = User::staticGet('nickname', $nickname);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}
	
	function email_exists($email) {
		$user = common_current_user();
		$other = User::staticGet('email', $email);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}
}
