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

require_once(INSTALLDIR.'/lib/openid.php');

class OpenidloginAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (common_logged_in()) {
			common_user_error(_t('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->start_openid_login();
		} else {
			$this->show_form();
		}
	}

	function show_form($error=NULL) {
		common_show_header(_t('OpenID Login'));
		if ($error) {
			common_element('div', array('class' => 'error'), $error);
		} else {
			common_element('div', 'instructions',
						   _t('Login with an OpenID account.'));
		}
		common_element_start('form', array('method' => 'POST',
										   'id' => 'openidlogin',
										   'action' => common_local_url('openidlogin')));
		common_input('openid_url', _t('OpenID URL'));
		common_submit('submit', _t('Login'));
		common_element_end('form');
		common_show_footer();
	}

	function start_openid_login() {
		# XXX: form token in $_SESSION to prevent XSS
		# XXX: login throttle
		$openid_url = $this->trimmed('openid_url');
		if (!common_valid_http_url($openid_url)) {
			$this->show_form(_t('OpenID must be a valid URL.'));
			return;
		}

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
		$process_url = common_local_url('finishopenidlogin');

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
							   '}');
				common_show_footer();
			}
		}
	}
}
