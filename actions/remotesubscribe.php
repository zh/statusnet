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
		
		if (!$yadis) {
			$this->show_form(_t('Not a valid profile URL (no YADIS document).'));
			return;
		}

		$omb = $this->getOmb($yadis);
		
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

	function getOmb($yadis) {
	    static $endpoints = array(OMB_ENDPOINT_UPDATEPROFILE, OMB_ENDPOINT_POSTNOTICE,
								  OAUTH_ENDPOINT_REQUEST, OAUTH_ENDPOINT_AUTHORIZE,
								  OAUTH_ENDPOINT_ACCESS);
		$omb = array();
		$services = $yadis->services(); # ordered by priority
		if (!$services) {
			return NULL;
		}
		
		foreach ($services as $service) {
			$types = $service->matchTypes($endpoints);
			foreach ($types as $type) {
				# We take the first one, since it's the highest priority
				if (!array_key_exists($type, $omb)) {
					# URIs is an array, priority-ordered
					$omb[$type] = $service->getURIs();
					# Special handling for request
					if ($type == OAUTH_ENDPOINT_REQUEST) {
						$nodes = $service->getElements('LocalID');
						if (!$nodes) {
							# error
							return NULL;
						}
						$omb['listener'] = $service->parser->content($nodes[0]);
					}
				}
			}
		}
		foreach ($endpoints as $ep) {
			if (!array_key_exists($ep, $omb)) {
				return NULL;
			}
		}
		if (!array_key_exists('listener', $omb)) {
			return NULL;
		}
		return $omb;
	}
	
	function request_token($omb) {
		$con = omb_oauth_consumer();

		$url = $omb[OAUTH_ENDPOINT_REQUEST][0];
		
		# XXX: Is this the right thing to do? Strip off GET params and make them
		# POST params? Seems wrong to me.
		
		$parsed = parse_url($url);
		$params = array();
		parse_str($parsed['query'], $params);

		$req = OAuthRequest::from_consumer_and_token($con, NULL, "POST", $url, $params);
		
		$req->set_parameter('omb_listener', $omb['listener']);
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
		
		$url = $omb[OAUTH_ENDPOINT_AUTHORIZE][0];
		
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
		$req->set_parameter('omb_listener', $omb['listener']);
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