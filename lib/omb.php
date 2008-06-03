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

require_once('OAuth.php');
require_once(INSTALLDIR.'/lib/oauthstore.php');

require_once(INSTALLDIR.'/classes/Consumer.php');
require_once(INSTALLDIR.'/classes/Nonce.php');
require_once(INSTALLDIR.'/classes/Token.php');

define('OAUTH_NAMESPACE', 'http://oauth.net/core/1.0/');
define('OMB_NAMESPACE', 'http://openmicroblogging.org/protocol/0.1');
define('OMB_VERSION_01', 'http://openmicroblogging.org/protocol/0.1');
define('OAUTH_DISCOVERY', 'http://oauth.net/discovery/1.0');

define('OMB_ENDPOINT_UPDATEPROFILE', OMB_NAMESPACE.'/updateProfile');
define('OMB_ENDPOINT_POSTNOTICE', OMB_NAMESPACE.'/postNotice');
define('OAUTH_ENDPOINT_REQUEST', OAUTH_NAMESPACE.'endpoint/request');
define('OAUTH_ENDPOINT_AUTHORIZE', OAUTH_NAMESPACE.'endpoint/authorize');
define('OAUTH_ENDPOINT_ACCESS', OAUTH_NAMESPACE.'endpoint/access');
define('OAUTH_ENDPOINT_RESOURCE', OAUTH_NAMESPACE.'endpoint/resource');
define('OAUTH_AUTH_HEADER', OAUTH_NAMESPACE.'parameters/auth-header');
define('OAUTH_POST_BODY', OAUTH_NAMESPACE.'parameters/post-body');
define('OAUTH_HMAC_SHA1', OAUTH_NAMESPACE.'signature/HMAC-SHA1');
	   
function omb_oauth_consumer() {
	static $con = null;
	if (!$con) {
		$con = new OAuthConsumer(common_root_url(), '');
	}
	return $con;
}

function omb_oauth_server() {
	static $server = null;
	if (!$server) {
		$server = new OAuthServer(new LaconicaOAuthDataStore());
		$server->add_signature_method(omb_hmac_sha1());
	}
	return $server;
}

function omb_hmac_sha1() {
	static $hmac_method = NULL;
	if (!$hmac_method) {
		$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
	}
	return $hmac_method;
}

function omb_get_services($xrd, $type) {
	return $xrd->services(array(omb_service_filter($type)));
}

function omb_service_filter($type) {
	return create_function('$s', 
						   'return omb_match_service($s, \''.$type.'\');');
}
	
function omb_match_service($service, $type) {
	return in_array($type, $service->getTypes());
}

function omb_service_uri($service) {
	if (!$service) {
		return NULL;
	}
	$uris = $service->getURIs();
	if (!$uris) {
		return NULL;
	}
	return $uris[0];
}

function omb_local_id($service) {
	if (!$service) {
		return NULL;
	}
	$els = $service->getElements('xrd:LocalID');
	if (!$els) {
		return NULL;
	}
	$el = $els[0];
	return $service->parser->content($el);
}
	
