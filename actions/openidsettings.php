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
require_once(INSTALLDIR.'/lib/openid.php');

class OpenidsettingsAction extends SettingsAction {

	function show_top($arr) {
		$msg = $arr[0];
		$success = $arr[1];
		
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('Manage your associated OpenIDs from here.'));
		}
		
		$this->settings_menu();
	}
	
	function show_form($msg=NULL, $success=false) {
		
		$user = common_current_user();
		
		common_show_header(_t('OpenID settings'), NULL, array($msg, $success),
						   array($this, 'show_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'openidadd',
										   'action' =>
										   common_local_url('openidsettings')));
		common_element('h2', NULL, _t('Add OpenID'));
		common_element('p', NULL,
					   _t('If you want to add an OpenID to your account, ' .
						  'enter it in the box below and click "Add".'));
		common_element_start('p');
		common_element('label', array('for' => 'openid_url'),
					   _t('OpenID URL'));
		common_element('input', array('name' => 'openid_url',
									  'type' => 'text',
									  'id' => 'openid_url'));
		common_element('input', array('type' => 'submit',
									  'id' => 'add',
									  'name' => 'add',
									  'class' => 'submit',
									  'value' => _t('Add')));
		common_element_end('p');
		common_element_end('form');

		$oid = new User_openid();
		$oid->user_id = $user->id;

		$cnt = $oid->find();

		if ($cnt > 0) {
			
			common_element('h2', NULL, _t('Remove OpenID'));
			
			if ($cnt == 1 && !$user->password) {

				common_element('p', NULL,
							   _t('Removing your only OpenID would make it impossible to log in! ' .
								  'If you need to remove it, add another OpenID first.'));
				common_element_start('p');
				common_element('a', array('href' => $oid->canonical),
							   $oid->display);
				common_element_end('p');
				
			} else {
			
				common_element('h2', NULL, _t('Remove OpenID'));
				common_element('p', NULL,
							   _t('You can remove an OpenID from your account '.
								  'by clicking the button marked "Remove".'));
				$idx = 0;
				
				while ($oid->fetch()) {
					common_element_start('form', array('method' => 'POST',
													   'id' => 'openiddelete' . $idx,
												   'action' =>
													   common_local_url('openidsettings')));
					common_element_start('p');
					common_element('a', array('href' => $oid->canonical),
								   $oid->display);
					common_element('input', array('type' => 'hidden',
												  'id' => 'openid_url'.$idx,
												  'name' => 'openid_url',
												  'value' => $oid->canonical));
					common_element('input', array('type' => 'submit',
												  'id' => 'remove'.$idx,
												  'name' => 'remove',
												  'class' => 'submit',
												  'value' => _t('Remove')));
					common_element_end('p');
					common_element_end('form');
					$idx++;
				}
			}
			
			common_show_footer();
		}
	}
	
	function handle_post() {
		if ($this->arg('add')) {
			$result = oid_authenticate($this->trimmed('openid_url'), 'finishaddopenid');
			if (is_string($result)) { # error message
				$this->show_form($result);
			}
		} else if ($this->arg('remove')) {
			$this->remove_openid();
		} else {
			$this->show_form(_t('Something weird happened.'));
		}
	}

	function remove_openid() {
		
		$openid_url = $this->trimmed('openid_url');
		$oid = User_openid::staticGet('canonical', $openid_url);
		if (!$oid) {
			$this->show_form(_t('No such OpenID.'));
			return;
		}
		$cur = common_current_user();
		if (!$cur || $oid->user_id != $cur->id) {
			$this->show_form(_t('That OpenID does not belong to you.'));
			return;
		}
		$oid->delete();
		$this->show_form(_t('OpenID removed.'), true);
		return;
	}
}