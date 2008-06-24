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

class RecoverpasswordAction extends Action {

    function handle($args) {
        parent::handle($args);
        if (common_logged_in()) {
			$this->client_error(_t('You are already logged in!'));
            return;
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        	if ($this->arg('recover')) {
            	$this->recover_password();
            } else if ($this->arg('reset')) {
            	$this->reset_password();
			} else {
				$this->client_error(_t('Unexpected form.'));
			}
		} else {
			if ($this->trimmed('code')) {
        		$this->check_code();
        	} else {
        		$this->show_form();
			}
		}
	}

	function check_code() {
		$code = $this->trimmed('code');
		$confirm = Confirm_address::staticGet($code);
		if ($confirm && $confirm->type == 'recover') {
			$user = User::staticGet($confirm->user_id);
			if ($user) {
				$result = $confirm->delete();
				if (!$result) {
					common_log_db_error($confirm, 'DELETE', __FILE__);
					common_server_error(_t('Error with confirmation code.'));
					return;
				}
				$this->set_temp_user($user);
				$this->show_password_form();
			}
		}
	}

	function set_temp_user(&$user) {
		common_ensure_session();
		$_SESSION['tempuser'] = $user->id;
	}

	function get_temp_user() {
		common_ensure_session();
		$user_id = $_SESSION['tempuser'];
		if ($user_id) {
			$user = User::staticGet($user_id);
		}
		return $user;
	}

	function clear_temp_user() {
		common_ensure_session();
		unset($_SESSION['tempuser']);
	}

	function show_top($msg=NULL) {
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('If you\'ve forgotten or lost your' .
						      ' password, you can get a new one sent ' .
						      ' the email address you have stored ' .
						      ' in your account.'));
		}
	}

	function show_password_top($msg=NULL) {
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('You\ve been identified . Enter a ' .
						      ' new password below. '));
		}
	}

	function show_form($msg=NULL) {

		common_show_header(_t('Recover password'), NULL,
		$msg, array($this, 'show_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'recoverpassword',
										   'action' => common_local_url('recoverpassword')));
		common_input('nicknameoremail', _t('Nickname or email'),
					 $this->trimmed('nicknameoremail'),
		             _t('Your nickname on this server, ' .
		                'or your registered email address.'));
		common_submit('recover', _t('Recover'));
		common_element_end('form');
		common_show_footer();
	}

	function show_password_form($msg=NULL) {

		common_show_header(_t('Reset password'), NULL,
		$msg, array($this, 'show_password_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'recoverpassword',
										   'action' => common_local_url('recoverpassword')));
		common_password('newpassword', _t('New password'),
						_t('6 or more characters, and don\'t forget it!'));
		common_password('confirm', _t('Confirm'),
						_t('Same as password above'));
		common_submit('reset', _t('Reset'));
		common_element_end('form');
		common_show_footer();
	}

	function recover_password() {
		$nore = $this->trimmed('nicknameoremail');
		if (!$nore) {
			$this->show_form(_t('Enter a nickname or email address.'));
			return;
		}
		$user = User::staticGet('email', common_canonical_email($nore));
		if (!$user) {
			$user = User::staticGet('nickname', common_canonical_nickname($nore));
		}

		if (!$user) {
			$this->show_form(_t('No such user.'));
			return;
		}
		if (!$user->email) {
			$this->client_error(_t('No registered email address for that user.'));
			return;
		}

		$confirm = new Confirm_address();
		$confirm->code = common_confirmation_code(128);
		$confirm->type = 'recover';
		$confirm->user_id = $user->id;
		$confirm->address = $user->email;

		if (!$confirm->insert()) {
			common_log_db_error($confirm, 'INSERT', __FILE__);
			$this->server_error(_t('Error saving address confirmation.'));
			return;
		}

		$body = "Hey, $user->nickname.";
		$body .= "\n\n";
		$body .= 'Someone just asked for a new password ' .
		         'for this account on ' . common_config('site', 'name') . '.';
		$body .= "\n\n";
		$body .= 'If it was you, and you want to confirm, use the URL below:';
		$body .= "\n\n";
		$body .= "\t".common_local_url('confirmaddress',
								   array('code' => $code));
		$body .= "\n\n";
		$body .= 'If not, just ignore this message.';
		$body .= "\n\n";
		$body .= 'Thanks for your time, ';
		$body .= "\n";
		$body .= common_config('site', 'name');
		$body .= "\n";

		return mail_to_user($user, _t('Password recovery requested'), $body);
	}

	function reset_password() {

		$user = $this->get_temp_user();

		if (!$user) {
			$this->client_error(_t('Unexpected password reset.'));
			return;
		}
		$password = $this->trimmed('password');
		$confirm = $this->trimmed('confirm');
		if (!$password || strlen($password) < 6) {
			$this->show_password_form(_t('Password must be 6 chars or more.'));
			return;
		}
		if ($password != $confirm) {
			$this->show_password_form(_t('Password and confirmation do not match.'));
			return;
		}

		# OK, we're ready to go

		$original = clone($user);

		$user->password = common_munge_password($newpassword, $user->id);

		if (!$user->update($original)) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Can\'t save new password.'));
			return;
		}

		$this->clear_temp_user();

		if (!common_set_user($user->nickname)) {
			common_server_error(_t('Error setting user.'));
			return;
		}

		common_real_login(true);

		common_show_header(_('Password saved.'));
		common_element('p', NULL, _t('New password successfully saved. ' .
		                             'You are now logged in.'));
		common_show_footer();
	}
}
