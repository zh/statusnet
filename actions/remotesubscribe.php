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

require_once(INSTALLDIR.'/lib/omb.php');
require_once('Auth/Yadis/Yadis.php');

class RemotesubscribeAction extends Action {
	
	function handle($args) {
		
		parent::handle($args);

		if (common_logged_in()) {
			common_user_error(_t('You can use the local subscription!'));
		    return;
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->remote_subscription();
		} else {
			$this->show_form();
		}
	}

	function show_form($err=NULL) {
		$nickname = $this->trimmed('nickname');
		common_show_header(_t('Remote subscribe'));
		if ($err) {
			common_element('div', 'error', $err);
		}
		common_element_start('form', array('id' => 'remotesubscribe', 'method' => 'POST',
										   'action' => common_local_url('remotesubscribe')));
		common_input('nickname', _t('User nickname'), $nickname);
		common_input('profile', _t('Profile URL'));
		common_submit('submit', _t('Subscribe'));
		common_element_end('form');
		common_show_footer();
	}
	
	function remote_subscription() {
		$user = $this->get_user();
		
		if (!$user) {
			$this->show_form(_t('No such user!'));
			return;
		}
		
		$profile = $this->trimmed('profile');
		
		if (!$profile) {
			$this->show_form(_t('No such user!'));
			return;
		}
		
		if (!Validate::uri($profile, array('allowed_schemes' => array('http', 'https')))) {
			$this->show_form(_t('Invalid profile URL (bad format)'));
			return;
		}
		
		$fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
		$yadis = Auth_Yadis_Yadis::discover($profile, $fetcher);

		common_debug('remotesubscribe.php: XRDS discovery failure? "'.$yadis->failed.'"');
					 
		if (!$yadis || $yadis->failed) {
			$this->show_form(_t('Not a valid profile URL (no YADIS document).'));
			return;
		}

        $xrds =& Auth_Yadis_XRDS::parseXRDS($yadis->response_text);

		if (!$xrds) {
			$this->show_form(_t('Not a valid profile URL (no XRDS defined).'));
			return;
		}
		
		common_debug('remotesubscribe.php: XRDS is "'.print_r($xrds,TRUE).'"');

		$omb = $this->getOmb($xrds);
		
		if (!$omb) {
			$this->show_form(_t('Not a valid profile URL (incorrect services).'));
			return;
		}
		
		list($token, $secret) = $this->request_token($omb);
		
		if (!$token || !$secret) {
			$this->show_form(_t('Couldn\'t get a request token.'));
			return;
		}
		
		$this->request_authorization($user, $omb, $token, $secret);
	}
	
	function get_user() {
		$user = NULL;
		$nickname = $this->trimmed('nickname');
		if ($nickname) {
			$user = User::staticGet('nickname', $nickname);
		}
		return $user;
	}

	function getOmb($xrds) {
		
	    static $omb_endpoints = array(OMB_ENDPOINT_UPDATEPROFILE, OMB_ENDPOINT_POSTNOTICE);
		static $oauth_endpoints = array(OAUTH_ENDPOINT_REQUEST, OAUTH_ENDPOINT_AUTHORIZE,
										OAUTH_ENDPOINT_ACCESS);
		$omb = array();

		# XXX: the following code could probably be refactored to eliminate dupes
		
		common_debug('remotesubscribe.php - looking for oauth discovery service');
		
		$oauth_service = $xrds->services(omb_service_filter(OAUTH_DISCOVERY));
		
		if (!$oauth_service) {
			common_debug('remotesubscribe.php - failed to find oauth discovery service');
			return NULL;
		}

		common_debug('remotesubscribe.php - looking for oauth discovery XRD');
		
		$xrd = $this->getXRD($oauth_service, $xrds);
		
		if (!$xrd) {
			common_debug('remotesubscribe.php - failed to find oauth discovery XRD');
			return NULL;
		}
		
		common_debug('remotesubscribe.php - adding OAuth services from XRD');
		
		if (!$this->addServices($xrd, $oauth_endpoints, $omb)) {
			common_debug('remotesubscribe.php - failed to add OAuth services');
			return NULL;
		}

		common_debug('remotesubscribe.php - looking for OMB discovery service');
		
		$omb_service = $xrds->services(omb_service_filter(OMB_NAMESPACE));

		if (!$omb_service) {
			common_debug('remotesubscribe.php - failed to find OMB discovery service');
			return NULL;
		}

		common_debug('remotesubscribe.php - looking for OMB discovery XRD');
		
		$xrd = $this->getXRD($omb_service, $xrds);

		if (!$xrd) {
			common_debug('remotesubscribe.php - failed to find OMB discovery XRD');
			return NULL;
		}
		
		common_debug('remotesubscribe.php - adding OMB services from XRD');
		
		if (!$this->addServices($xrd, $omb_endpoints, $omb)) {
			common_debug('remotesubscribe.php - failed to add OMB services');
			return NULL;
		}
		
		# XXX: check that we got all the services we needed
		
		foreach (array_merge($omb_endpoints, $oauth_endpoints) as $type) {
			if (!array_key_exists($type, $omb)) {
				return NULL;
			}
		}
		
		if (!omb_local_id($omb[OAUTH_ENDPOINT_REQUEST])) {
			return NULL;
		}
		
		return $omb;
	}

	function getXRD($main_service, $main_xrds) {
		$uri = omb_service_uri($main_service);
		if (strpos($uri, "#") !== 0) {
			# FIXME: more rigorous handling of external service definitions
			return NULL;
		}
		$id = substr($uri, 1);
		$nodes = $main_xrds->allXrdNodes;
		$parser = $main_xrds->parser;
		foreach ($nodes as $node) {
			$attrs = $parser->attributes($node);
			if (array_key_exists('xml:id', $attrs) &&
				$attrs['xml:id'] == $id) {
				return new Auth_Yadis_XRDS($parser, array($node));
			}
		}
		return NULL;
	}

	function addServices($xrd, $types, &$omb) {
		foreach ($types as $type) {
			$matches = $xrd->services(omb_service_filter($type));
			if ($matches) {
				$omb[$type] = $services[0];
			} else {
				# no match for type
				return false;
			}
		}
		return true;
	}
	
	function request_token($omb) {
		$con = omb_oauth_consumer();

		$url = omb_service_uri($omb[OAUTH_ENDPOINT_REQUEST]);
		
		# XXX: Is this the right thing to do? Strip off GET params and make them
		# POST params? Seems wrong to me.
		
		$parsed = parse_url($url);
		$params = array();
		parse_str($parsed['query'], $params);

		$req = OAuthRequest::from_consumer_and_token($con, NULL, "POST", $url, $params);

		$listener = omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]);
		
		if (!$listener) {
			return NULL;
		}
		
		$req->set_parameter('omb_listener', $listener);
		$req->set_parameter('omb_version', OMB_VERSION_01);
		
		# XXX: test to see if endpoint accepts this signature method

		$req->sign_request(omb_hmac_sha1(), $con, NULL);
		
		# We re-use this tool's fetcher, since it's pretty good
		
		$fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
		$result = $fetcher->post($req->get_normalized_http_url(),
								 $req->to_postdata());
		
		if ($result->status != 200) {
			return NULL;
		}

		parse_str($result->body, $return);
		
		return array($return['oauth_token'], $return['oauth_token_secret']);
	}
	
	function request_authorization($user, $omb, $token, $secret) {
		global $config; # for license URL
		
		$con = omb_oauth_consumer();
		$tok = new OAuthToken($token, $secret);
		
		$url = omb_service_uri($omb[OAUTH_ENDPOINT_AUTHORIZE]);
		
		# XXX: Is this the right thing to do? Strip off GET params and make them
		# POST params? Seems wrong to me.
		
		$parsed = parse_url($url);
		$params = array();
		parse_str($parsed['query'], $params);

		$req = OAuthRequest::from_consumer_and_token($con, $tok, 'GET', $url, $params);
		
		# We send over a ton of information. This lets the other
		# server store info about our user, and it lets the current
		# user decide if they really want to authorize the subscription.
		
		$req->set_parameter('omb_version', OMB_VERSION_01);
		$req->set_parameter('omb_listener', omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]));
		$req->set_parameter('omb_listenee', $user->uri);
		$req->set_parameter('omb_listenee_profile', common_profile_url($user->nickname));
		$req->set_parameter('omb_listenee_nickname', $user->nickname);
		$req->set_parameter('omb_listenee_license', $config['license']['url']);
		$profile = $user->getProfile();
		if ($profile->fullname) {
			$req->set_parameter('omb_listenee_fullname', $profile->fullname);
		}
		if ($profile->homepage) {
			$req->set_parameter('omb_listenee_homepage', $profile->homepage);
		}
		if ($profile->bio) {
			$req->set_parameter('omb_listenee_bio', $profile->bio);
		}
		if ($profile->location) {
			$req->set_parameter('omb_listenee_location', $profile->location);
		}
		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
		if ($avatar) {
			$req->set_parameter('omb_listenee_avatar', $avatar->url);
		}

		$nonce = $this->make_nonce();
		
		$req->set_parameter('oauth_callback', common_local_url('finishremotesubscribe',
															   array('nonce' => $nonce)));
							
		# XXX: test to see if endpoint accepts this signature method

		$req->sign_request(omb_hmac_sha1(), $con, $tok);
		
		# store all our info here

		$omb['listenee'] = $user->nickname;
		$omb['token'] = $token;
		$omb['secret'] = $secret;
		
		$_SESSION[$nonce] = $omb;
		
		# Redirect to authorization service
		
		common_redirect($req->to_url());
		return;
	}
}