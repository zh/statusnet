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
			common_user_error(_t('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->try_register();
		} else {
			$this->show_form();
		}
	}

	function try_register() {
		$nickname = $this->arg('nickname');
		$password = $this->arg('password');
		$confirm = $this->arg('confirm');
		$email = $this->arg('email');

		# Input scrubbing
		
		$nickname = common_canonical_nickname($nickname);
		$email = common_canonical_email($email);
		
		if ($this->nickname_exists($nickname)) {
			$this->show_form(_t('Username already exists.'));
		} else if ($this->email_exists($email)) {
			$this->show_form(_t('Email address already exists.'));
		} else if ($password != $confirm) {
			$this->show_form(_t('Passwords don\'t match.'));
		} else if ($this->register_user($nickname, $password, $email)) {
			common_set_user($nickname);
			common_redirect(common_local_url('profilesettings'));
		} else {
			$this->show_form(_t('Invalid username or password.'));
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
		# TODO: wrap this in a transaction!
		$profile = new Profile();
		$profile->nickname = $nickname;
		$profile->created = time();
		$id = $profile->insert();
		if (!$id) {
			return FALSE;
		}
		$user = new User();
		$user->id = $id;
		$user->nickname = $nickname;
		$user->password = common_munge_password($password, $id);
		$user->email = $email;
		$user->created = time();
		$result = $user->insert();
		if (!$result) {
			# Try to clean up...
			$profile->delete();
		}
		return $result;
	}
	
	function show_form($error=NULL) {
		
		common_show_header(_t('Register'));
		common_element_start('form', array('method' => 'POST',
										   'id' => 'login',
										   'action' => common_local_url('register')));
		common_element('label', array('for' => 'nickname'),
					   _t('Name'));
		common_element('input', array('name' => 'nickname',
									  'type' => 'text',
									  'id' => 'nickname'));
		common_element('label', array('for' => 'password'),
					   _t('Password'));
		common_element('input', array('name' => 'password',
									  'type' => 'password',									  
									  'id' => 'password'));
		common_element('label', array('for' => 'confirm'),
					   _t('Confirm'));
		common_element('input', array('name' => 'confirm',
									  'type' => 'password',									  
									  'id' => 'confirm'));
		common_element('label', array('for' => 'email'),
					   _t('Email'));
		common_element('input', array('name' => 'email',
									  'type' => 'text',									  
									  'id' => 'email'));
		common_element('input', array('name' => 'submit',
									  'type' => 'submit',
									  'id' => 'submit',
									  'value' => _t('Login')));
		common_element('input', array('name' => 'cancel',
									  'type' => 'button',
									  'id' => 'cancel',
									  'value' => _t('Cancel')));
		common_element_end('form');
	}
}
