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
		$fullname = $this->trimmed('fullname');
		$homepage = $this->trimmed('homepage');
		$bio = $this->trimmed('bio');
		$location = $this->trimmed('location');

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
			$this->show_form(_('Nickname already in use. Try another one.'));
		} else if (!User::allowed_nickname($nickname)) {
			$this->show_form(_('Not a valid nickname.'));
		} else if ($this->email_exists($email)) {
			$this->show_form(_('Email address already exists.'));
		} else if (!is_null($homepage) && (strlen($homepage) > 0) &&
				   !Validate::uri($homepage, array('allowed_schemes' => array('http', 'https')))) {
			$this->show_form(_('Homepage is not a valid URL.'));
			return;
		} else if (!is_null($fullname) && strlen($fullname) > 255) {
			$this->show_form(_('Full name is too long (max 255 chars).'));
			return;
		} else if (!is_null($bio) && strlen($bio) > 140) {
			$this->show_form(_('Bio is too long (max 140 chars).'));
			return;
		} else if (!is_null($location) && strlen($location) > 255) {
			$this->show_form(_('Location is too long (max 255 chars).'));
			return;
		} else if ($password != $confirm) {
			$this->show_form(_('Passwords don\'t match.'));
		} else if ($user = $this->register_user($nickname, $password, $email, $fullname, $homepage, $bio, $location)) {
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
			$this->show_success();
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

	function register_user($nickname, $password, $email, $fullname, $homepage, $bio, $location) {

		$profile = new Profile();

		$profile->query('BEGIN');

		$profile->nickname = $nickname;
		$profile->profileurl = common_profile_url($nickname);
		if ($fullname) {
			$profile->fullname = $fullname;
		}
		if ($homepage) {
			$profile->homepage = $homepage;
		}
		if ($bio) {
			$profile->bio = $bio;
		}
		if ($location) {
			$profile->location = $location;
		}
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
		common_input('nickname', _('Nickname'), $this->trimmed('nickname'),
					 _('1-64 lowercase letters or numbers, no punctuation or spaces. Required.'));
		common_password('password', _('Password'),
						_('6 or more characters. Required.'));
		common_password('confirm', _('Confirm'),
						_('Same as password above. Required.'));
		common_input('email', _('Email'), $this->trimmed('email'),
					 _('Used only for updates, announcements, and password recovery'));
		common_input('fullname', _('Full name'),
					 $this->trimmed('fullname'),
					  _('Longer name, preferably your "real" name'));
		common_input('homepage', _('Homepage'),
					 $this->trimmed('homepage'),
					 _('URL of your homepage, blog, or profile on another site'));
		common_textarea('bio', _('Bio'),
						$this->trimmed('bio'),
						 _('Describe yourself and your interests in 140 chars'));
		common_input('location', _('Location'),
					 $this->trimmed('location'),
					 _('Where you are, like "City, State (or Region), Country"'));
		common_checkbox('rememberme', _('Remember me'), 
						$this->boolean('rememberme'),
		                _('Automatically login in the future; not for shared computers!'));
		common_element_start('p');
		$attrs = array('type' => 'checkbox',
					   'id' => 'license',
					   'name' => 'license',
					   'value' => 'true');
		if ($this->boolean('license')) {
			$attrs['checked'] = 'checked';
		}
		common_element('input', $attrs);
	    common_text(_('My text and files are available under '));
		common_element('a', array(href => $config['license']['url']),
					   $config['license']['title']);
		common_text(_(' except this private data: password, email address, IM address, phone number.'));
		common_element_end('p');
		common_submit('submit', _('Register'));
		common_element_end('form');
		common_show_footer();
	}
						
	function show_success() {
		$nickname = $this->arg('nickname');
		common_show_header(_('Registration successful'));
		common_element_start('div', 'success');
		$instr = sprintf(_('Congratulations, %s! And welcome to %%site.name%%. From here, you may want to...' .
						   '* Go to [your profile](%s) and post your first message.' .
						   '* Add a [Jabber/GTalk address](%%action.imsettings%%) so you can send notices through instant messages.' .
						   '* (Search for people)[%%action.peoplesearch%%] that you may know or that share your interests. ' .
						   '* Update your [profile settings](%%action.profilesettings%%) to tell others more about you. ' .
						   '* Read over the [online docs](%%doc.help%%) for features you may have missed. ' .
						   'Thanks for signing up and we hope you enjoy using this service.'),
						 $nickname, common_local_url('showstream', array('nickname' => $nickname)));
		common_raw(common_markup_to_html($instr));
		$have_email = $this->trimmed('email');
		if ($have_email) {
			$emailinstr = _t('(You should receive a message by email momentarily, with ' .
							 'instructions on how to confirm your email address.)');
			common_raw(common_markup_to_html($emailinstr));
		}
	}
						
}
