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

class FinishremotesubscribeAction extends Action {
	
	function handle($args) {
		
		parent::handle($args);

		if (common_logged_in()) {
			common_user_error(_('You can use the local subscription!'));
		    return;
		}
		
		$omb = $_SESSION['oauth_authorization_request'];
		
		if (!$omb) {
			common_user_error(_('Not expecting this response!'));
			return;
		}

		common_debug('stored request: '.print_r($omb,true), __FILE__);
		
		$req = OAuthRequest::from_request();

		$token = $req->get_parameter('oauth_token');

		# I think this is the success metric
		
		if ($token != $omb['token']) {
			common_user_error(_('Not authorized.'));
			return;
		}
		
		$version = $req->get_parameter('omb_version');
		
		if ($version != OMB_VERSION_01) {
			common_user_error(_('Unknown version of OMB protocol.'));
			return;
		}
		
		$nickname = $req->get_parameter('omb_listener_nickname');
		
		if (!$nickname) {
			common_user_error(_('No nickname provided by remote server.'));
			return;
		}

		$profile_url = $req->get_parameter('omb_listener_profile');
		
		if (!$profile_url) {
			common_user_error(_('No profile URL returned by server.'));
			return;
		}

		if (!Validate::uri($profile_url, array('allowed_schemes' => array('http', 'https')))) {
			common_user_error(_('Invalid profile URL returned by server.'));
			return;
		}

		common_debug('listenee: "'.$omb['listenee'].'"', __FILE__);
		
		$user = User::staticGet('nickname', $omb['listenee']);
		
		if (!$user) {
			common_user_error(_('User being listened to doesn\'t exist.'));
			return;
		}
		
		$fullname = $req->get_parameter('omb_listener_fullname');
		$homepage = $req->get_parameter('omb_listener_homepage');
		$bio = $req->get_parameter('omb_listener_bio');
		$location = $req->get_parameter('omb_listener_location');
		$avatar_url = $req->get_parameter('omb_listener_avatar');

		list($newtok, $newsecret) = $this->access_token($omb);
		
		if (!$newtok || !$newsecret) {
			common_user_error(_('Couldn\'t convert request tokens to access tokens.'));
			return;
		}
		
		# XXX: possible attack point; subscribe and return someone else's profile URI
		
		$remote = Remote_profile::staticGet('uri', $omb['listener']);
		
		if ($remote) {
			$exists = true;
			$profile = Profile::staticGet($remote->id);
			$orig_remote = clone($remote);
			$orig_profile = clone($profile);
			# XXX: compare current postNotice and updateProfile URLs to the ones
			# stored in the DB to avoid (possibly...) above attack
		} else {
			$exists = false;
			$remote = new Remote_profile();
			$remote->uri = $omb['listener'];
			$profile = new Profile();
		}

		$profile->nickname = $nickname;
		$profile->profileurl = $profile_url;
		
		if ($fullname) {
			$profile->fullname = $fullname;
		}
		if ($homepage) {
			$profile->homepage = $homepage;
		}
		if ($bio) {
			$profile->bio = $bio;
		}
		if ($location) {
			$profile->location = $location;
		}
		
		if ($exists) {
			$profile->update($orig_profile);
		} else {
			$profile->created = DB_DataObject_Cast::dateTime(); # current time
			$id = $profile->insert();
			if (!$id) {
				common_server_error(_('Error inserting new profile'));
				return;
			}
			$remote->id = $id;
		}

		if ($avatar_url) {
			if (!$this->add_avatar($profile, $avatar_url)) {
				common_server_error(_('Error inserting avatar'));
				return;
			}
		}

		$remote->postnoticeurl = $omb['post_notice_url'];
		$remote->updateprofileurl = $omb['update_profile_url'];

		if ($exists) {
			if (!$remote->update($orig_remote)) {
				common_server_error(_('Error updating remote profile'));
				return;
			}
		} else {
			$remote->created = DB_DataObject_Cast::dateTime(); # current time
			if (!$remote->insert()) {
				common_server_error(_('Error inserting remote profile'));
				return;
			}
		}
		
		$sub = new Subscription();
		$sub->subscriber = $remote->id;
		$sub->subscribed = $user->id;
		$sub->token = $newtok;
		$sub->secret = $newsecret;
		$sub->created = DB_DataObject_Cast::dateTime(); # current time
		
		if (!$sub->insert()) {
			common_user_error(_('Couldn\'t insert new subscription.'));
			return;
		}

		# Clear the data
		unset($_SESSION['oauth_authorization_request']);
		
		# If we show subscriptions in reverse chron order, this should
		# show up close to the top of the page
		
		common_redirect(common_local_url('subscribers', array('nickname' =>
															 $user->nickname)));
	}
	
	function add_avatar($profile, $url) {
		$temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
		copy($url, $temp_filename);
		return $profile->setOriginal($temp_filename);
	}
	
	function access_token($omb) {

		common_debug('starting request for access token', __FILE__);
		
		$con = omb_oauth_consumer();
		$tok = new OAuthToken($omb['token'], $omb['secret']);

		common_debug('using request token "'.$tok.'"', __FILE__);
		
		$url = $omb['access_token_url'];

		common_debug('using access token url "'.$url.'"', __FILE__);
		
		# XXX: Is this the right thing to do? Strip off GET params and make them
		# POST params? Seems wrong to me.
		
		$parsed = parse_url($url);
		$params = array();
		parse_str($parsed['query'], $params);

		$req = OAuthRequest::from_consumer_and_token($con, $tok, "POST", $url, $params);
		
		$req->set_parameter('omb_version', OMB_VERSION_01);
		
		# XXX: test to see if endpoint accepts this signature method

		$req->sign_request(omb_hmac_sha1(), $con, $tok);
		
		# We re-use this tool's fetcher, since it's pretty good

		common_debug('posting to access token url "'.$req->get_normalized_http_url().'"', __FILE__);
		common_debug('posting request data "'.$req->to_postdata().'"', __FILE__);
		
		$fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
		$result = $fetcher->post($req->get_normalized_http_url(),
								 $req->to_postdata());

		common_debug('got result: "'.print_r($result,TRUE).'"', __FILE__);
		
		if ($result->status != 200) {
			return NULL;
		}

		parse_str($result->body, $return);
		
		return array($return['oauth_token'], $return['oauth_token_secret']);
	}
}