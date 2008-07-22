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

class UpdateprofileAction extends Action {
	
	function handle($args) {
		parent::handle($args);
		try {
			$req = OAuthRequest::from_request();
			# Note: server-to-server function!
			$server = omb_oauth_server();
			list($consumer, $token) = $server->verify_request($req);
			if ($this->update_profile($req, $consumer, $token)) {
				print "omb_version=".OMB_VERSION_01;
			}
		} catch (OAuthException $e) {
			$this->server_error($e->getMessage());
			return;
		}
	}

	function update_profile($req, $consumer, $token) {
		$version = $req->get_parameter('omb_version');
		if ($version != OMB_VERSION_01) {
			$this->client_error(_('Unsupported OMB version'), 400);
			return false;
		}
		# First, check to see if listenee exists
		$listenee =  $req->get_parameter('omb_listenee');
		$remote = Remote_profile::staticGet('uri', $listenee);
		if (!$remote) {
			$this->client_error(_('Profile unknown'), 404);
			return false;
		}
		# Second, check to see if they should be able to post updates!
		# We see if there are any subscriptions to that remote user with
		# the given token.

		$sub = new Subscription();
		$sub->subscribed = $remote->id;
		$sub->token = $token->key;
		if (!$sub->find(true)) {
			$this->client_error(_('You did not send us that profile'), 403);
			return false;
		}

		$profile = Profile::staticGet('id', $remote->id);
		if (!$profile) {
			# This one is our fault
			$this->server_error(_('Remote profile with no matching profile'), 500);
			return false;
		}
		$nickname = $req->get_parameter('omb_listenee_nickname');
		if ($nickname && !Validate::string($nickname, array('min_length' => 1,
															'max_length' => 64,
															'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->client_error(_('Nickname must have only lowercase letters and numbers and no spaces.'));
			return false;
		}
		$license = $req->get_parameter('omb_listenee_license');
		if ($license && !common_valid_http_url($license)) {
			$this->client_error(sprintf(_("Invalid license URL '%s'"), $license));
			return false;
		}
		$profile_url = $req->get_parameter('omb_listenee_profile');
		if ($profile_url && !common_valid_http_url($profile_url)) {
			$this->client_error(sprintf(_("Invalid profile URL '%s'."), $profile_url));
			return false;
		}
		# optional stuff
		$fullname = $req->get_parameter('omb_listenee_fullname');
		if ($fullname && strlen($fullname) > 255) {
			$this->client_error(_("Full name is too long (max 255 chars)."));
			return false;
		}
		$homepage = $req->get_parameter('omb_listenee_homepage');
		if ($homepage && (!common_valid_http_url($homepage) || strlen($homepage) > 255)) {
			$this->client_error(sprintf(_("Invalid homepage '%s'"), $homepage));
			return false;
		}
		$bio = $req->get_parameter('omb_listenee_bio');
		if ($bio && strlen($bio) > 140) {
			$this->client_error(_("Bio is too long (max 140 chars)."));
			return false;
		}
		$location = $req->get_parameter('omb_listenee_location');
		if ($location && strlen($location) > 255) {
			$this->client_error(_("Location is too long (max 255 chars)."));
			return false;
		}
		$avatar = $req->get_parameter('omb_listenee_avatar');
		if ($avatar) {
			if (!common_valid_http_url($avatar) || strlen($avatar) > 255) {
				$this->client_error(sprintf(_("Invalid avatar URL '%s'"), $avatar));
				return false;
			}
			$size = @getimagesize($avatar);
			if (!$size) {
				$this->client_error(sprintf(_("Can't read avatar URL '%s'"), $avatar));
				return false;
			}
			if ($size[0] != AVATAR_PROFILE_SIZE || $size[1] != AVATAR_PROFILE_SIZE) {
				$this->client_error(sprintf(_("Wrong size image at '%s'"), $avatar));
				return false;
			}
			if (!in_array($size[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG,
										  IMAGETYPE_PNG))) {
				$this->client_error(sprintf(_("Wrong image type for '%s'"), $avatar));
				return false;
			}
		}

		$orig_profile = clone($profile);

		if ($nickname) {
			$profile->nickname = $nickname;
		}
		if ($profile_url) {
			$profile->profileurl = $profile_url;
		}
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

		if (!$profile->update($orig_profile)) {
			$this->server_error(_('Could not save new profile info'), 500);
			return false;
		} else {
			if ($avatar) {
				$temp_filename = tempnam(sys_get_temp_dir(), 'listenee_avatar');
				copy($avatar, $temp_filename);
				if (!$profile->setOriginal($temp_filename)) {
					$this->server_error(_('Could not save avatar info'), 500);
					return false;
				}
			}
			header('HTTP/1.1 200 OK');
			header('Content-type: text/plain');
			print 'Updated profile';
			print "\n";
			return true;
		}
	}
}
