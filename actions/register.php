<?php

class RegisterAction extends Action {
	
	function handle($args) {
		parent::handle($args);
		
		if (common_logged_in()) {
			common_user_error(_t('Already logged in.'));
		} else if ($this->arg('METHOD') == 'POST') {
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
			common_redirect(common_local_url('settings'));
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
		$email = common_canonicalize_email($email);
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
		
		common_show_header(_t('Login'));
		common_start_element('form', array('method' => 'POST',
										   'id' => 'login',
										   'action' => common_local_url('login')));
		common_element('label', array('for' => 'username'),
					   _t('Name'));
		common_element('input', array('name' => 'username',
									  'type' => 'text',
									  'id' => 'username'));
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
									  'id' => 'submit'),
					   _t('Login'));
		common_element('input', array('name' => 'cancel',
									  'type' => 'button',
									  'id' => 'cancel'),
					   _t('Cancel'));
	}
}
