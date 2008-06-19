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

# About one year cookie expiry

define('OPENID_COOKIE_EXPIRY', round(365.25 * 24 * 60 * 60));
define('OPENID_COOKIE_KEY', 'lastusedopenid');
	   
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

function oid_clear_last() {
	if (oid_get_last()) {
		oid_set_last('');
	}
}

function oid_set_last($openid_url) {
	global $config;
	setcookie(OPENID_COOKIE_KEY, $openid_url,
			  time() + OPENID_COOKIE_EXPIRY,
			  '/' . $config['site']['path'] . '/',
			  $config['site']['server']);
}

function oid_get_last() {
	return $_COOKIE[OPENID_COOKIE_KEY];
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

function oid_get_user($openid_url) {
	$user = NULL;
	$oid = User_openid::staticGet('canonical', $openid_url);
	if ($oid) {
		$user = User::staticGet('id', $oid->user_id);
	}
	return $user;
}

function oid_check_immediate($openid_url, $backto=NULL) {
	if (!$backto) {
		$action = $_REQUEST['action'];
		$args = clone($_GET);
		unset($args['action']);
		$backto = common_local_url($action, $args);
	}
	common_debug('going back to "' . $backto . '"', __FILE__);
	
	common_ensure_session();
	
	$_SESSION['openid_immediate_backto'] = $backto;
	common_debug('passed-in variable is "' . $backto . '"', __FILE__);
	common_debug('session variable is "' . $_SESSION['openid_immediate_backto'] . '"', __FILE__);
	
	oid_authenticate($openid_url,
					 'finishimmediate',
					 true);
}

function oid_authenticate($openid_url, $returnto, $immediate=false) {

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
												   $process_url,
												   $immediate);
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
											   $immediate, array('id' => $form_id));
		
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

# update a user from sreg parameters

function oid_update_user(&$user, &$sreg) {
		
	$profile = $user->getProfile();
	
	$orig_profile = clone($profile);
	
	if ($sreg['fullname'] && strlen($sreg['fullname']) <= 255) {
		$profile->fullname = $sreg['fullname'];
	}
	
	if ($sreg['country']) {
		if ($sreg['postcode']) {
			# XXX: use postcode to get city and region
			# XXX: also, store postcode somewhere -- it's valuable!
			$profile->location = $sreg['postcode'] . ', ' . $sreg['country'];
		} else {
			$profile->location = $sreg['country'];
		}
	}
	
	# XXX save language if it's passed
	# XXX save timezone if it's passed
	
	if (!$profile->update($orig_profile)) {
		common_server_error(_t('Error saving the profile.'));
		return;
	}
	
	$orig_user = clone($user);
	
	if ($sreg['email'] && Validate::email($sreg['email'], true)) {
		$user->email = $sreg['email'];
	}
	
	if (!$user->update($orig_user)) {
		common_server_error(_t('Error saving the user.'));
		return;
	}
}
