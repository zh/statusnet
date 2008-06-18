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

class FinishaddopenidAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
		} else {
			$this->try_login();
		}
	}

	function try_login() {

		$consumer = oid_consumer();

		$response = $consumer->complete(common_local_url('finishaddopenid'));

		if ($response->status == Auth_OpenID_CANCEL) {
			$this->message(_t('OpenID authentication cancelled.'));
			return;
		} else if ($response->status == Auth_OpenID_FAILURE) {
			// Authentication failed; display the error message.
			$this->message(_t('OpenID authentication failed: ') . $response->message);
		} else if ($response->status == Auth_OpenID_SUCCESS) {
			// This means the authentication succeeded; extract the
			// identity URL and Simple Registration data (if it was
			// returned).
			$display = $response->getDisplayIdentifier();
			$canonical = ($response->endpoint->canonicalID) ?
			  $response->endpoint->canonicalID : $response->getDisplayIdentifier();

			$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);

			if ($sreg_resp) {
				$sreg = $sreg_resp->contents();
			}

			$user = $this->get_user($canonical);

			if ($user) {
				$this->message(_t('This OpenID is already associated with user "') . $user->nickname . _t('"'));
			} else {
				$user = common_current_user();
				if (!$this->connect_user($user, $display, $canonical)) {
					$this->message(_t('Error connecting user'));
					return;
				}
				if ($sreg) {
					if (!$this->update_user($user, $sreg)) {
						$this->message(_t('Error updating profile'));
						return;
					}
				}
				# success!
				common_redirect(common_local_url('openidsettings'));
			}
		}
	}

	function message($msg) {
		common_show_header(_t('OpenID Login'));
		common_element('p', NULL, $msg);
		common_show_footer();
	}

	function get_user($canonical) {
		$user = NULL;
		$oid = User_openid::staticGet('canonical', $canonical);
		if ($oid) {
			$user = User::staticGet('id', $oid->user_id);
		}
		return $user;
	}

	function update_user($user, $sreg) {

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
			return false;
		}

		$orig_user = clone($user);

		if ($sreg['email'] && Validate::email($sreg['email'], true)) {
			$user->email = $sreg['email'];
		}

		if (!$user->update($orig_user)) {
			common_server_error(_t('Error saving the user.'));
			return false;
		}
		
		return true;
	}

	function connect_user($user, $display, $canonical) {

		$oid = new User_openid();
		$oid->display = $display;
		$oid->canonical = $canonical;
		$oid->user_id = $user->id;
		$oid->created = DB_DataObject_Cast::dateTime();

		common_debug('Saving ' . print_r($oid, TRUE), __FILE__);
		
		if (!$oid->insert()) {
			return false;
		}
	}
}
