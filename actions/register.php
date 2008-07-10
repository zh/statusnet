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

class RegisterAction extends Action {

	function handle($args) {
		parent::handle($args);

		if (common_logged_in()) {
			common_user_error(_('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->try_register();
		} else {
			$this->show_form();
		}
	}

	function try_register() {
		$nickname = $this->trimmed('nickname');
		$email = $this->trimmed('email');

		# We don't trim these... whitespace is OK in a password!

		$password = $this->arg('password');
		$confirm = $this->arg('confirm');

		# Input scrubbing

		$nickname = common_canonical_nickname($nickname);
		$email = common_canonical_email($email);

		if (!$this->boolean('license')) {
			$this->show_form(_('You can\'t register if you don\'t agree to the license.'));
		} else if ($email && !Validate::email($email, true)) {
			$this->show_form(_('Not a valid email address.'));
		} else if (!Validate::string($nickname, array('min_length' => 1,
													  'max_length' => 64,
													  'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->show_form(_('Nickname must have only lowercase letters and numbers and no spaces.'));
		} else if ($this->nickname_exists($nickname)) {
			$this->show_form(_('Nickname already exists.'));
		} else if (!User::allowed_nickname($nickname)) {
			$this->show_form(_('Not a valid nickname.'));
		} else if ($this->email_exists($email)) {
			$this->show_form(_('Email address already exists.'));
		} else if ($password != $confirm) {
			$this->show_form(_('Passwords don\'t match.'));
		} else {
			$user = $this->register_user($nickname, $password, $email);
			if (!$user) {
				$this->show_form(_('Invalid username or password.'));
				return;
			}
			# success!
			if (!common_set_user($user)) {
				common_server_error(_('Error setting user.'));
				return;
			}
			# this is a real login
			common_real_login(true);
			if ($this->boolean('rememberme')) {
				common_debug('Adding rememberme cookie for ' . $nickname);
				common_rememberme($user);
			}
			common_redirect(common_local_url('profilesettings'));
		} else {
			$this->show_form(_('Invalid username or password.'));
		}
	}

	# checks if *CANONICAL* nickname exists

	function nickname_exists($nickname) {
		$user = User::staticGet('nickname', $nickname);
		return ($user !== false);
	}

	# checks if *CANONICAL* email exists

	function email_exists($email) {
		$email = common_canonical_email($email);
		$user = User::staticGet('email', $email);
		return ($user !== false);
	}

	function register_user($nickname, $password, $email) {

		$profile = new Profile();

		$profile->query('BEGIN');

		$profile->nickname = $nickname;
		$profile->profileurl = common_profile_url($nickname);
		$profile->created = DB_DataObject_Cast::dateTime(); # current time

		$id = $profile->insert();

		if (!$id) {
			common_log_db_error($profile, 'INSERT', __FILE__);
		    return FALSE;
		}
		$user = new User();
		$user->id = $id;
		$user->nickname = $nickname;
		$user->password = common_munge_password($password, $id);
		$user->created =  DB_DataObject_Cast::dateTime(); # current time
		$user->uri = common_user_uri($user);

		$result = $user->insert();

		if (!$result) {
			common_log_db_error($user, 'INSERT', __FILE__);
			return FALSE;
		}

		if ($email) {

			$confirm = new Confirm_address();
			$confirm->code = common_confirmation_code(128);
			$confirm->user_id = $user->id;
			$confirm->address = $email;
			$confirm->address_type = 'email';

			$result = $confirm->insert();
			if (!$result) {
				common_log_db_error($confirm, 'INSERT', __FILE__);
				return FALSE;
			}
		}

		$profile->query('COMMIT');

		if ($email) {
			mail_confirm_address($confirm->code,
								 $profile->nickname,
								 $email);
		}

		return $user;
	}

	function show_top($error=NULL) {
		if ($error) {
			common_element('p', 'error', $error);
		} else {
			common_element('div', 'instructions',
						   _('You can create a new account to start posting notices.'));
		}
	}

	function show_form($error=NULL) {
		global $config;

		common_show_header(_('Register'), NULL, $error, array($this, 'show_top'));
		common_element_start('form', array('method' => 'post',
										   'id' => 'login',
										   'action' => common_local_url('register')));
		common_input('nickname', _('Nickname'), NULL,
					 _('1-64 lowercase letters or numbers, no punctuation or spaces'));
		common_password('password', _('Password'),
						_('6 or more characters'));
		common_password('confirm', _('Confirm'),
						_('Same as password above'));
		common_input('email', _('Email'), NULL,
					 _('Used only for updates, announcements, and password recovery'));
		common_checkbox('rememberme', _('Remember me'), false,
		                _('Automatically login in the future; ' .
		                   'not for shared computers!'));
		common_element_start('p');
		common_element('input', array('type' => 'checkbox',
									  'id' => 'license',
									  'name' => 'license',
									  'value' => 'true'));
		common_text(_('My text and files are available under '));
		common_element('a', array(href => $config['license']['url']),
					   $config['license']['title']);
		common_text(_(' except this private data: password, email address, IM address, phone number.'));
		common_element_end('p');
		common_submit('submit', _('Register'));
		common_element_end('form');
		common_show_footer();
	}
}
