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

if (!defined('LACONICA')) { exit(1) }

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
