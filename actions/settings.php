<?php

class SettingsAction extends Action {
	
	function handle($args) {
		parent::handle($args);
		if ($this->arg('METHOD') == 'POST') {
			$nickname = $this->arg('nickname');
			$fullname = $this->arg('fullname');
			$email = $this->arg('email');
			$homepage = $this->arg('homepage');
			$bio = $this->arg('bio');
			$location = $this->arg('location');
			$oldpass = $this->arg('oldpass');
			$password = $this->arg('password');
			$confirm = $this->arg('confirm');
			
			if ($password) {
				if ($password != $confirm) {
					$this->show_form(_t('Passwords don\'t match.'));
				}
			} else if (
			
			$error = $this->save_settings($nickname, $fullname, $email, $homepage,
										  $bio, $location, $password);
			if (!$error) {
				$this->show_form(_t('Settings saved.'), TRUE);
			} else {
				$this->show_form($error);
			}
		} else {
			$this->show_form();
		}
				