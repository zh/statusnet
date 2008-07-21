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

class EmailsettingsAction extends SettingsAction {

	function get_instructions() {
		return _('Manage how you get email from %%site.name%%.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$this->form_header(_('Email Settings'), $msg, $success);
		common_element_start('form', array('method' => 'post',
										   'id' => 'emailsettings',
										   'action' =>
										   common_local_url('emailsettings')));

		common_element('h2', NULL, _('Address'));

		if ($user->email) {
			common_element_start('p');
			common_element('span', 'address confirmed', $user->email);
			common_element('span', 'input_instructions',
			               _('Current confirmed email address.'));
			common_hidden('email', $user->email);
			common_element_end('p');
			common_submit('remove', _('Remove'));
		} else {
			$confirm = $this->get_confirmation();
			if ($confirm) {
				common_element_start('p');
				common_element('span', 'address unconfirmed', $confirm->address);
				common_element('span', 'input_instructions',
							   _('Awaiting confirmation on this address. Check your inbox (and spam box!) for a message with further instructions.'));
				common_hidden('email', $confirm->address);
				common_element_end('p');
				common_submit('cancel', _('Cancel'));
			} else {
				common_input('email', _('Email Address'),
							 ($this->arg('email')) ? $this->arg('email') : NULL,
							 _('Email address, like "UserName@example.org"'));
				common_submit('add', _('Add'));
			}
		}

		if ($user->email) {
			common_element('h2', NULL, _('Incoming email'));
			
			if ($user->incomingemail) {
				common_element_start('p');
				common_element('span', 'address', $user->incomingemail);
				common_element('span', 'input_instructions',
							   _('Send email to this address to post new notices.'));
				common_element_end('p');
				common_submit('removeincoming', _('Remove'));
			}
			
			common_element_start('p');
			common_element('span', 'input_instructions',
						   _('Make a new email address for posting to; cancels the old one.'));
			common_element_end('p');
			common_submit('newincoming', _('New'));
		}
		
		common_element('h2', NULL, _('Preferences'));

		common_checkbox('emailnotifysub',
		                _('Send me notices of new subscriptions through email.'),
		                $user->emailnotifysub);
		
		common_submit('save', _('Save'));
		
		common_element_end('form');
		common_show_footer();
	}

	function get_confirmation() {
		$user = common_current_user();
		$confirm = new Confirm_address();
		$confirm->user_id = $user->id;
		$confirm->address_type = 'email';
		if ($confirm->find(TRUE)) {
			return $confirm;
		} else {
			return NULL;
		}
	}

	function handle_post() {

		if ($this->arg('save')) {
			$this->save_preferences();
		} else if ($this->arg('add')) {
			$this->add_address();
		} else if ($this->arg('cancel')) {
			$this->cancel_confirmation();
		} else if ($this->arg('remove')) {
			$this->remove_address();
		} else if ($this->arg('removeincoming')) {
			$this->remove_incoming();
		} else if ($this->arg('newincoming')) {
			$this->new_incoming();
		} else {
			$this->show_form(_('Unexpected form submission.'));
		}
	}

	function save_preferences() {

		$emailnotifysub = $this->boolean('emailnotifysub');

		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);

		$user->emailnotifysub = $emailnotifysub;

		$result = $user->update($original);

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t update user.'));
			return;
		}

		$user->query('COMMIT');

		$this->show_form(_('Preferences saved.'), true);
	}

	function add_address() {

		$user = common_current_user();

		$email = $this->trimmed('email');

		# Some validation

		if (!$email) {
			$this->show_form(_('No email address.'));
			return;
		}

		$email = common_canonical_email($email);

		if (!$email) {
		    $this->show_form(_('Cannot normalize that email address'));
		    return;
		}
		if (!Validate::email($email, true)) {
		    $this->show_form(_('Not a valid email address'));
		    return;
		} else if ($user->email == $email) {
		    $this->show_form(_('That is already your email address.'));
		    return;
		} else if ($this->email_exists($email)) {
		    $this->show_form(_('That email address already belongs to another user.'));
		    return;
		}

  		$confirm = new Confirm_address();
   		$confirm->address = $email;
   		$confirm->address_type = 'email';
   		$confirm->user_id = $user->id;
   		$confirm->code = common_confirmation_code(64);

		$result = $confirm->insert();

		if ($result === FALSE) {
			common_log_db_error($confirm, 'INSERT', __FILE__);
			common_server_error(_('Couldn\'t insert confirmation code.'));
			return;
		}

		mail_confirm_address($confirm->code,
							 $user->nickname,
							 $email);

		$msg = _('A confirmation code was sent to the email address you added. Check your inbox (and spam box!) for the code and instructions on how to use it.');

		$this->show_form($msg, TRUE);
	}

	function cancel_confirmation() {
		$email = $this->arg('email');
		$confirm = $this->get_confirmation();
		if (!$confirm) {
			$this->show_form(_('No pending confirmation to cancel.'));
			return;
		}
		if ($confirm->address != $email) {
			$this->show_form(_('That is the wrong IM address.'));
			return;
		}

        $result = $confirm->delete();

        if (!$result) {
			common_log_db_error($confirm, 'DELETE', __FILE__);
            $this->server_error(_('Couldn\'t delete email confirmation.'));
            return;
        }

        $this->show_form(_('Confirmation cancelled.'), TRUE);
	}

	function remove_address() {

		$user = common_current_user();
		$email = $this->arg('email');

		# Maybe an old tab open...?

		if ($user->email != $email) {
		    $this->show_form(_('That is not your email address.'));
		    return;
		}

		$user->query('BEGIN');
		$original = clone($user);
		$user->email = NULL;
		$result = $user->updateKeys($original);
		if (!$result) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t update user.'));
			return;
		}
		$user->query('COMMIT');

		$this->show_form(_('The address was removed.'), TRUE);
	}

	function remove_incoming() {
		$user = common_current_user();
		
		if (!$user->incomingemail) {
			$this->show_form(_('No incoming email address.'));
			return;
		}
		
		$orig = clone($user);
		$user->incomingemail = NULL;

		if (!$user->updateKeys($orig)) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			$this->server_error(_("Couldn't update user record."));
		}
		
		$this->show_form(_('Incoming email address removed.'), TRUE);
	}

	function new_incoming() {
		$user = common_current_user();
		
		$orig = clone($user);
		$user->incomingemail = mail_new_incoming_address();
		
		if (!$user->updateKeys($orig)) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			$this->server_error(_("Couldn't update user record."));
		}

		$this->show_form(_('New incoming email address added.'), TRUE);
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
