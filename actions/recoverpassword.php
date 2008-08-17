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

# You have 24 hours to claim your password

define(MAX_RECOVERY_TIME, 24 * 60 * 60);

class RecoverpasswordAction extends Action {

    function handle($args) {
        parent::handle($args);
        if (common_logged_in()) {
			$this->client_error(_('You are already logged in!'));
            return;
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        	if ($this->arg('recover')) {
            	$this->recover_password();
            } else if ($this->arg('reset')) {
            	$this->reset_password();
			} else {
				$this->client_error(_('Unexpected form submission.'));
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

		if (!$confirm) {
			$this->client_error(_('No such recovery code.'));
			return;
		}
		if ($confirm->address_type != 'recover') {
			$this->client_error(_('Not a recovery code.'));
			return;
		}

		$user = User::staticGet($confirm->user_id);

		if (!$user) {
			$this->server_error(_('Recovery code for unknown user.'));
			return;
		}

		$touched = strtotime($confirm->modified);
		$email = $confirm->address;

		# Burn this code

		$result = $confirm->delete();

		if (!$result) {
			common_log_db_error($confirm, 'DELETE', __FILE__);
			common_server_error(_('Error with confirmation code.'));
			return;
		}

		# These should be reaped, but for now we just check mod time
		# Note: it's still deleted; let's avoid a second attempt!

		if ((time() - $touched) > MAX_RECOVERY_TIME) {
			$this->client_error(_('This confirmation code is too old. ' .
			                       'Please start again.'));
			return;
		}

		# If we used an outstanding confirmation to send the email,
		# it's been confirmed at this point.

		if (!$user->email) {
			$orig = clone($user);
			$user->email = $email;
			$result = $user->updateKeys($orig);
			if (!$result) {
				common_log_db_error($user, 'UPDATE', __FILE__);
				$this->server_error(_('Could not update user with confirmed email address.'));
				return;
			}
		}

		# Success!

		$this->set_temp_user($user);
		$this->show_password_form();
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
            common_element('div', 'error', $msg);
		} else {
			common_element('div', 'instructions',
						   _('If you\'ve forgotten or lost your' .
						      ' password, you can get a new one sent to' .
						      ' the email address you have stored ' .
						      ' in your account.'));
		}
	}

	function show_password_top($msg=NULL) {
		if ($msg) {
            common_element('div', 'error', $msg);
		} else {
			common_element('div', 'instructions',
						   _('You\'ve been identified. Enter a ' .
						      ' new password below. '));
		}
	}

	function show_form($msg=NULL) {

		common_show_header(_('Recover password'), NULL,
		$msg, array($this, 'show_top'));

		common_element_start('form', array('method' => 'post',
										   'id' => 'recoverpassword',
										   'action' => common_local_url('recoverpassword')));
		common_input('nicknameoremail', _('Nickname or email'),
					 $this->trimmed('nicknameoremail'),
		             _('Your nickname on this server, ' .
		                'or your registered email address.'));
		common_submit('recover', _('Recover'));
		common_element_end('form');
		common_show_footer();
	}

	function show_password_form($msg=NULL) {

		common_show_header(_('Reset password'), NULL,
		$msg, array($this, 'show_password_top'));

		common_element_start('form', array('method' => 'post',
										   'id' => 'recoverpassword',
										   'action' => common_local_url('recoverpassword')));
		common_password('newpassword', _('New password'),
						_('6 or more characters, and don\'t forget it!'));
		common_password('confirm', _('Confirm'),
						_('Same as password above'));
		common_submit('reset', _('Reset'));
		common_element_end('form');
		common_show_footer();
	}

	function recover_password() {
		$nore = $this->trimmed('nicknameoremail');
		if (!$nore) {
			$this->show_form(_('Enter a nickname or email address.'));
			return;
		}

		$user = User::staticGet('email', common_canonical_email($nore));

		if (!$user) {
			$user = User::staticGet('nickname', common_canonical_nickname($nore));
		}

		# See if it's an unconfirmed email address

		if (!$user) {
			$confirm_email = Confirm_address::staticGet('address', common_canonical_email($nore));
			if ($confirm_email && $confirm_email->address_type == 'email') {
				$user = User::staticGet($confirm_email->user_id);
			}
		}

		if (!$user) {
			$this->show_form(_('No user with that email address or username.'));
			return;
		}

		# Try to get an unconfirmed email address if they used a user name

		if (!$user->email && !$confirm_email) {
			$confirm_email = Confirm_address::staticGet('user_id', $user->id);
			if ($confirm_email && $confirm_email->address_type != 'email') {
				# Skip non-email confirmations
				$confirm_email = NULL;
			}
		}

		if (!$user->email && !$confirm_email) {
			$this->client_error(_('No registered email address for that user.'));
			return;
		}

		# Success! We have a valid user and a confirmed or unconfirmed email address

		$confirm = new Confirm_address();
		$confirm->code = common_confirmation_code(128);
		$confirm->address_type = 'recover';
		$confirm->user_id = $user->id;
		$confirm->address = (isset($user->email)) ? $user->email : $confirm_email->address;

		if (!$confirm->insert()) {
			common_log_db_error($confirm, 'INSERT', __FILE__);
			$this->server_error(_('Error saving address confirmation.'));
			return;
		}

		$body = "Hey, $user->nickname.";
		$body .= "\n\n";
		$body .= 'Someone just asked for a new password ' .
		         'for this account on ' . common_config('site', 'name') . '.';
		$body .= "\n\n";
		$body .= 'If it was you, and you want to confirm, use the URL below:';
		$body .= "\n\n";
		$body .= "\t".common_local_url('recoverpassword',
								   array('code' => $confirm->code));
		$body .= "\n\n";
		$body .= 'If not, just ignore this message.';
		$body .= "\n\n";
		$body .= 'Thanks for your time, ';
		$body .= "\n";
		$body .= common_config('site', 'name');
		$body .= "\n";

		mail_to_user($user, _('Password recovery requested'), $body, $confirm->address);

		common_show_header(_('Password recovery requested'));
		common_element('p', NULL,
		               _('Instructions for recovering your password ' .
		                  'have been sent to the email address registered to your ' .
		                  'account.'));
		common_show_footer();
	}

	function reset_password() {

		$user = $this->get_temp_user();

		if (!$user) {
			$this->client_error(_('Unexpected password reset.'));
			return;
		}

		$newpassword = $this->trimmed('newpassword');
		$confirm = $this->trimmed('confirm');

		if (!$newpassword || strlen($newpassword) < 6) {
			$this->show_password_form(_('Password must be 6 chars or more.'));
			return;
		}
		if ($newpassword != $confirm) {
			$this->show_password_form(_('Password and confirmation do not match.'));
			return;
		}

		# OK, we're ready to go

		$original = clone($user);

		$user->password = common_munge_password($newpassword, $user->id);

		if (!$user->update($original)) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Can\'t save new password.'));
			return;
		}

		$this->clear_temp_user();

		if (!common_set_user($user->nickname)) {
			common_server_error(_('Error setting user.'));
			return;
		}

		common_real_login(true);

		common_show_header(_('Password saved.'));
		common_element('p', NULL, _('New password successfully saved. ' .
		                             'You are now logged in.'));
		common_show_footer();
	}
}
