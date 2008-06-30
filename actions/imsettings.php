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
require_once(INSTALLDIR.'/lib/jabber.php');

class ImsettingsAction extends SettingsAction {

	function get_instructions() {
		_t('You can send and receive notices through '.
		   'Jabber/GTalk [instant messages](%%doc.im%%). Configure '.
		   'your address and settings below.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$this->form_header(_t('IM Settings'), $msg, $success);
		common_element_start('form', array('method' => 'POST',
										   'id' => 'imsettings',
										   'action' =>
										   common_local_url('imsettings')));

		common_element('h2', NULL, _t('Address'));

		if ($user->jabber) {
			common_element_start('p');
			common_element('span', 'address confirmed', $user->jabber);
			common_element('span', 'input_instructions',
			               _t('Current confirmed Jabber/GTalk address.'));
			common_hidden('jabber', $user->jabber);
			common_element_end('p');
			common_submit('remove', 'Remove');
		} else {
			$confirm = $this->get_confirmation();
			if ($confirm) {
				common_element_start('p');
				common_element('span', 'address unconfirmed', $confirm->address);
				common_element('span', 'input_instructions',
			  	             _t('Awaiting confirmation on this address. Check your ' .
			  	                'Jabber/GTalk account for a message with further ' .
			  	                'instructions. (Did you add '  . jabber_daemon_address() .
								' to your buddy list?)'));
				common_hidden('jabber', $confirm->address);
				common_element_end('p');
				common_submit('cancel', _t('Cancel'));
			} else {
				common_input('jabber', _t('IM Address'),
						 	($this->arg('jabber')) ? $this->arg('jabber') : NULL,
						 _t('Jabber or GTalk address, like "UserName@example.org". ' .
						    'First, make sure to add ' . jabber_daemon_address() .
						    ' to your buddy list in your IM client or on GTalk.'));
				common_submit('add', 'Add');
			}
		}

		common_element('h2', NULL, _t('Preferences'));

		common_checkbox('jabbernotify',
		                _t('Send me notices through Jabber/GTalk.'),
		                $user->jabbernotify);
		common_checkbox('updatefrompresence',
		                _t('Post a notice when my Jabber/GTalk status changes.'),
		                $user->updatefrompresence);
		common_submit('save', _t('Save'));

		common_element_end('form');
		common_show_footer();
	}

	function get_confirmation() {
		$user = common_current_user();
		$confirm = new Confirm_address();
		$confirm->user_id = $user->id;
		$confirm->address_type = 'jabber';
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
		} else {
			$this->show_form(_t('Unexpected form submission.'));
		}
	}

	function save_preferences() {

		$jabbernotify = $this->boolean('jabbernotify');
		$updatefrompresence = $this->boolean('updatefrompresence');

		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);

		$user->jabbernotify = $jabbernotify;
		$user->updatefrompresence = $updatefrompresence;

		$result = $user->update($original);

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt update user.'));
			return;
		}

		$user->query('COMMIT');

		$this->show_form(_t('Preferences saved.'), true);
	}

	function add_address() {

		$user = common_current_user();

		$jabber = $this->trimmed('jabber');

		# Some validation

		if (!$jabber) {
			$this->show_form(_t('No Jabber ID.'));
			return;
		}

		$jabber = jabber_normalize_jid($jabber);

		if (!$jabber) {
		    $this->show_form(_('Cannot normalize that Jabber ID'));
		    return;
		}
		if (!jabber_valid_base_jid($jabber)) {
		    $this->show_form(_('Not a valid Jabber ID'));
		    return;
		} else if ($user->jabber == $jabber) {
		    $this->show_form(_('That is already your Jabber ID.'));
		    return;
		} else if ($this->jabber_exists($jabber)) {
		    $this->show_form(_('Jabber ID already belongs to another user.'));
		    return;
		}

  		$confirm = new Confirm_address();
   		$confirm->address = $jabber;
   		$confirm->address_type = 'jabber';
   		$confirm->user_id = $user->id;
   		$confirm->code = common_confirmation_code(64);

		$result = $confirm->insert();

		if ($result === FALSE) {
			common_log_db_error($confirm, 'INSERT', __FILE__);
			common_server_error(_t('Couldnt insert confirmation code.'));
			return;
		}

		# XXX: queue for offline sending

		jabber_confirm_address($confirm->code,
							   $user->nickname,
							   $jabber);

		# XXX: I18N

		$msg = 'A confirmation code was sent to the IM address you added. ' .
			' You must approve ' . jabber_daemon_address() .
			' for sending messages to you.';

		$this->show_form($msg, TRUE);
	}

	function cancel_confirmation() {
		$jabber = $this->arg('jabber');
		$confirm = $this->get_confirmation();
		if (!$confirm) {
			$this->show_form(_t('No pending confirmation to cancel.'));
			return;
		}
		if ($confirm->address != $jabber) {
			$this->show_form(_t('That is the wrong IM address.'));
			return;
		}

        $result = $confirm->delete();

        if (!$result) {
			common_log_db_error($confirm, 'DELETE', __FILE__);
            $this->server_error(_t('Couldn\'t delete email confirmation.'));
            return;
        }

        $this->show_form(_t('Confirmation cancelled.'), TRUE);
	}

	function remove_address() {

		$user = common_current_user();
		$jabber = $this->arg('jabber');

		# Maybe an old tab open...?

		if ($user->jabber != $jabber) {
		    $this->show_form(_t('That is not your Jabber ID.'));
		    return;
		}

		$user->query('BEGIN');
		$original = clone($user);
		$user->jabber = NULL;
		$result = $user->updateKeys($original);
		if (!$result) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt update user.'));
			return;
		}
		$user->query('COMMIT');

		# Unsubscribe to the old address

		jabber_special_presence('unsubscribe', $jabber);

		$this->show_form(_t('The address was removed.'), TRUE);
	}

	function jabber_exists($jabber) {
		$user = common_current_user();
		$other = User::staticGet('jabber', $jabber);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}
}
