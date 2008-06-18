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

	function show_form($msg=NULL, $success=false) {
		
		$user = common_current_user();
		
		common_show_header(_t('OpenID settings'), NULL, NULL, array($this, 'settings_menu'));

		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('Manage your associated OpenIDs from here.'));
		}
		common_element_start('form', array('method' => 'POST',
										   'id' => 'openidadd',
										   'action' =>
										   common_local_url('openidsettings')));
		common_element('h2', NULL, _t('Add OpenID'));
		common_element('p', NULL,
					   _t('If you want to add an OpenID to your account, ',
						  'enter it in the box below and click "Add".'));
		common_input('openid_url', _t('OpenID URL'));
		common_submit('add', _t('Add'));
		common_element_end('form');

		$oid = new User_openid();
		$oid->user_id = $user->id;
		
		if ($oid->find()) {
			
			common_element('h2', NULL, _t('OpenID'));
			common_element('p', NULL,
						   _t('You can remove an OpenID from your account ',
							  'by clicking the button marked "Delete" next to it.'));
			$idx = 0;
			
			while ($oid->fetch()) {
				common_element_start('p');
				common_element_start('form', array('method' => 'POST',
												   'id' => 'openiddelete-' . $idx,
												   'action' =>
												   common_local_url('openidsettings')));
				common_element('a', array('href' => $oid->canonical),
							   $oid->display);
				common_hidden('openid_url', $oid->canonical);
				common_submit('remove', _t('Remove'));
				common_element_end('form');
				common_element_end('p');
				$idx++;
			}
		}
		
		common_show_footer();
	}

	function handle_post() {
		if ($this->arg('add')) {
			$this->add_openid();
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
		$this->show_form(_t('OpenID removed.', true));
		return;
	}
	
	function add_openid() {
		
		$openid_url = $this->trimmed('openid_url');

		$consumer = oid_consumer();

		if (!$consumer) {
			common_server_error(_t('Cannot instantiate OpenID consumer object.'));
			return;
		}

		common_ensure_session();

		$auth_request = $consumer->begin($openid_url);

		// Handle failure status return values.
		if (!$auth_request) {
			$this->show_form(_t('Not a valid OpenID.'));
			return;
		} else if (Auth_OpenID::isFailure($auth_request)) {
			$this->show_form(_t('OpenID failure: ') . $auth_request->message);
			return;
		}

		$sreg_request = Auth_OpenID_SRegRequest::build(// Required
													   array(),
													   // Optional
													   array('nickname',
															 'email',
															 'fullname',
															 'language',
															 'timezone',
															 'postcode',
															 'country'));

		if ($sreg_request) {
			$auth_request->addExtension($sreg_request);
		}

		$trust_root = common_root_url();
		$process_url = common_local_url('finishaddopenid');

		if ($auth_request->shouldSendRedirect()) {
			$redirect_url = $auth_request->redirectURL($trust_root,
													   $process_url);
			if (!$redirect_url) {
			} else if (Auth_OpenID::isFailure($redirect_url)) {
				$this->show_form(_t('Could not redirect to server: ') . $redirect_url->message);
				return;
			} else {
				common_redirect($redirect_url);
			}
		} else {
			// Generate form markup and render it.
			$form_id = 'openid_message';
			$form_html = $auth_request->formMarkup($trust_root, $process_url,
												   false, array('id' => $form_id));
			
			# XXX: This is cheap, but things choke if we don't escape ampersands
			# in the HTML attributes
			
			$form_html = preg_replace('/&/', '&amp;', $form_html);
			
			// Display an error if the form markup couldn't be generated;
			// otherwise, render the HTML.
			if (Auth_OpenID::isFailure($form_html)) {
				$this->show_form(_t('Could not create OpenID form: ') . $form_html->message);
			} else {
				common_show_header(_t('OpenID Auto-Submit'));
				common_element('p', 'instructions',
							   _t('This form should automatically submit itself. '.
								  'If not, click the submit button to go to your '.
								  'OpenID provider.'));
				common_raw($form_html);
				common_element('script', NULL,
							   '$(document).ready(function() { ' .
							   '    $("#'. $form_id .'").submit(); '.
							   '});');
				common_show_footer();
			}
		}
	}
}