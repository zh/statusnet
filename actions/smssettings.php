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
require_once(INSTALLDIR.'/actions/emailsettingsaction.php');

class SmssettingsAction extends EmailsettingsAction {

	function get_instructions() {
		return _('You can receive SMS messages through email from %%site.name%%.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$this->form_header(_('SMS Settings'), $msg, $success);
		common_element_start('form', array('method' => 'post',
										   'id' => 'smssettings',
										   'action' =>
										   common_local_url('smssettings')));

		common_element('h2', NULL, _('Address'));

		if ($user->sms) {
			common_element_start('p');
			$carrier = $user->getCarrier();
			common_element('span', 'address confirmed', $user->sms . ' (' . $carrier->name . ')');
			common_element('span', 'input_instructions',
			               _('Current confirmed SMS-enabled phone number.'));
			common_hidden('sms', $user->sms);
			common_hidden('carrier', $user->carrier);
			common_element_end('p');
			common_submit('remove', _('Remove'));
		} else {
			$confirm = $this->get_confirmation();
			if ($confirm) {
				$carrier = Sms_carrier::staticGet($confirm->address_extra);
				common_element('span', 'address unconfirmed', $confirm->address . ' (' . $carrier->name . ')');
				common_element('span', 'input_instructions',
							   _('Awaiting confirmation on this phone number.'));
				common_hidden('sms', $user->sms);
				common_hidden('carrier', $user->carrier);
				common_element_end('p');
				common_submit('cancel', _('Cancel'));
			} else {
				common_input('sms', _('SMS Phone number'),
							 ($this->arg('sms')) ? $this->arg('sms') : NULL,
							 _('Phone number, no punctuation or spaces, with area code'));
				$this->carrier_select();
				common_submit('add', _('Add'));
			}
		}

		if ($user->sms) {
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
		
		common_checkbox('smsnotify',
						_('Send me notices through SMS; I understand I may incur charges from my carrier.'),
						$user->smsnotify);
			
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

		$smsnotify = $this->boolean('emailpost');
		
		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);

		$user->smsnotify = $smsnotify;

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

		$sms = $this->trimmed('sms');
		$carrier_id = $this->trimmed('carrier');
		
		# Some validation

		if (!$sms) {
			$this->show_form(_('No phone number.'));
			return;
		}

		if (!$carrier_id) {
			$this->show_form(_('No carrier selected.'));
			return;
		}
		
		$sms = common_canonical_sms($sms);
		
		if ($user->sms == $sms) {
		    $this->show_form(_('That is already your phone number.'));
		    return;
		} else if ($this->sms_exists($sms)) {
		    $this->show_form(_('That phone number already belongs to another user.'));
		    return;
		}

  		$confirm = new Confirm_address();
   		$confirm->address = $sms;
   		$confirm->address_extra = $carrier_id;
   		$confirm->address_type = 'sms';
   		$confirm->user_id = $user->id;
   		$confirm->code = common_confirmation_code(64);

		$result = $confirm->insert();

		if ($result === FALSE) {
			common_log_db_error($confirm, 'INSERT', __FILE__);
			common_server_error(_('Couldn\'t insert confirmation code.'));
			return;
		}

		$carrier = Sms_carrier::staticGet($carrier_id);
		
		mail_confirm_address($confirm->code,
							 $user->nickname,
							 $carrier->toEmailAddress($sms));

		$msg = _('A confirmation code was sent to the phone number you added. Check your inbox (and spam box!) for the code and instructions on how to use it.');

		$this->show_form($msg, TRUE);
	}

	function cancel_confirmation() {
		
		$sms = $this->trimmed('sms');
		$carrier = $this->trimmed('carrier');
		
		$confirm = $this->get_confirmation();
		
		if (!$confirm) {
			$this->show_form(_('No pending confirmation to cancel.'));
			return;
		}
		if ($confirm->address != $sms || $confirm->address_extra != $carrier) {
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
		$sms = $this->arg('sms');
		$carrier = $this->arg('carrier');
		
		# Maybe an old tab open...?

		if ($user->sms != $sms) {
		    $this->show_form(_('That is not your phone number.'));
		    return;
		}

		$user->query('BEGIN');
		$original = clone($user);
		$user->sms = NULL;
		$user->carrier = NULL;		
		$result = $user->updateKeys($original);
		if (!$result) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t update user.'));
			return;
		}
		$user->query('COMMIT');

		$this->show_form(_('The address was removed.'), TRUE);
	}
	
	function sms_exists($sms) {
		$user = common_current_user();
		$other = User::staticGet('sms', $sms);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}

	function carrier_select() {
		$carrier = new Sms_carrier();
		$cnt = $carrier->find();

		common_element_start('p');
		common_element('label', array('for' => 'carrier'));
		common_element_start('select', array('name' => 'carrier',
											 'id' => 'carrier'));
		common_element('option', array('value' => 0),
					   _('Select a carrier'));
		while ($carrier->fetch()) {
			common_element('option', array('value' => $carrier->id),
						   $carrier->name);
		}
		common_element_end('select');
		common_element_end('p');
		common_element('span', 'input_instructions',
					   sprintf(_('Mobile carrier for your phone. '.
								 'If you know a carrier that accepts ' . 
								 'SMS over email but isn\'t listed here, ' .
								 'send email to let us know at %s.'),
							   common_config('site', 'email')));
	}
}
