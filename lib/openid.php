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

require_once(INSTALLDIR.'/classes/User_openid.php');

require_once('Auth/OpenID.php');
require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/SReg.php');
require_once('Auth/OpenID/MySQLStore.php');

function oid_store() {
    static $store = NULL;
	if (!$store) {
		# Can't be called statically
		$user = new User();
		$conn = $user->getDatabaseConnection();
		$store = new Auth_OpenID_MySQLStore($conn);
	}
	return $store;
}

function oid_consumer() {
	$store = oid_store();
	$consumer = new Auth_OpenID_Consumer($store);
	return $consumer;
}

function oid_link_user($id, $canonical, $display) {
	
	$oid = new User_openid();
	$oid->user_id = $id;
	$oid->canonical = $canonical;
	$oid->display = $display;
	$oid->created = DB_DataObject_Cast::dateTime();
		
	if (!$oid->insert()) {
		$err = PEAR::getStaticProperty('DB_DataObject','lastError');
		common_debug('DB error ' . $err->code . ': ' . $err->message, __FILE__);
		return false;
	}
	
	return true;
}

function oid_authenticate($openid_url, $returnto) {
		
	$consumer = oid_consumer();
	
	if (!$consumer) {
		common_server_error(_t('Cannot instantiate OpenID consumer object.'));
		return false;
	}
	
	common_ensure_session();
	
	$auth_request = $consumer->begin($openid_url);
	
	// Handle failure status return values.
	if (!$auth_request) {
		return _t('Not a valid OpenID.');
	} else if (Auth_OpenID::isFailure($auth_request)) {
		return _t('OpenID failure: ') . $auth_request->message;
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
	
	$trust_root = common_local_url('public');
	$process_url = common_local_url($returnto);
	
	if ($auth_request->shouldSendRedirect()) {
		$redirect_url = $auth_request->redirectURL($trust_root,
												   $process_url);
		if (!$redirect_url) {
		} else if (Auth_OpenID::isFailure($redirect_url)) {
			return _t('Could not redirect to server: ') . $redirect_url->message;
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
