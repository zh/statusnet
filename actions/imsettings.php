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

	function show_top($arr) {
		$msg = $arr[0];
		$success = $arr[1];
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('You can send and receive notices through '.
							  'Jabber/GTalk instant messages. Configure '.
							  'your address and settings below.'));
		}
		$this->settings_menu();
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		common_show_header(_t('IM settings'), NULL, array($msg, $success),
						   array($this, 'show_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'imsettings',
										   'action' =>
										   common_local_url('imsettings')));
		# too much common patterns here... abstractable?
		common_input('jabber', _t('IM Address'),
					 ($this->arg('jabber')) ? $this->arg('jabber') : $user->jabber,
					 _t('Jabber or GTalk address, like "UserName@example.org"'));
		common_checkbox('jabbernotify',
		                _t('Send me notices through Jabber/GTalk.'));
		common_checkbox('updatefrompresence',
		                _t('Post a notice when my Jabber/GTalk status changes.'));
		common_submit('submit', _t('Save'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		$jabber = $this->trimmed('jabber');
		$jabbernotify = $this->boolean('jabbernotify');
		$updatefrompresence = $this->boolean('updatefrompresence');

		# Some validation
		
		if ($jabber) {
			$jabber = jabber_normalize_jid($jabber);
			if (!$jabber) {
			    $this->show_form(_('Cannot normalize that Jabber ID'));
			    return;
			}
			if (!jabber_valid_base_jid($jabber)) {
			    $this->show_form(_('Not a valid Jabber ID'));
			    return;
		    } else if ($this->jabber_exists($jabber)) {
			    $this->show_form(_('Jabber ID already belongs to another user.'));
			    return;
			}
		}

		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);
		
		$user->jabbernotify = $jabbernotify;
		$user->updatefrompresence = $updatefrompresence;

		$result = $user->update($original); # For key columns

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt update user.'));
			return;
		}

		$confirmation_sent = false;
		
		if ($user->jabber != $jabber) {
			
			if ($jabber) {
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
				
				# XXX: optionally queue for offline sending
				
				jabber_confirm_address($confirm->code,
									   $user->nickname,
									   $jabber);
									   
				if ($result === FALSE) {
					common_log_db_error($confirm, 'INSERT', __FILE__);
					common_server_error(_t('Couldnt insert confirmation code.'));
					return;
				}
				
				$confirmation_sent = false;
			} else {
				# Clearing the ID is free
				$user->jabber = NULL;
				$result = $user->updateKeys($original);
				if ($result === FALSE) {
					common_log_db_error($user, 'UPDATE', __FILE__);
					common_server_error(_t('Couldnt update user.'));
					return;
				}
			}
		}
		
		$user->query('COMMIT');

        $msg = ($confirmation_sent) ? 
		                  _t('Settings saved. A confirmation code was ' .
		                     ' sent to the IM address you added. ') :
		                  _t('Settings saved.');
		                  
		$this->show_form($msg, TRUE);
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
