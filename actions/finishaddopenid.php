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
			common_user_error(_('Not logged in.'));
		} else {
			$this->try_login();
		}
	}
	
	function try_login() {

		$consumer =& oid_consumer();

		$response = $consumer->complete(common_local_url('finishaddopenid'));

		if ($response->status == Auth_OpenID_CANCEL) {
			$this->message(_('OpenID authentication cancelled.'));
			return;
		} else if ($response->status == Auth_OpenID_FAILURE) {
			// Authentication failed; display the error message.
			$this->message(sprintf(_('OpenID authentication failed: %s'), $response->message));
		} else if ($response->status == Auth_OpenID_SUCCESS) {

			$display = $response->getDisplayIdentifier();
			$canonical = ($response->endpoint && $response->endpoint->canonicalID) ?
			  $response->endpoint->canonicalID : $display;

			$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);

			if ($sreg_resp) {
				$sreg = $sreg_resp->contents();
			}

			$cur =& common_current_user();
			$other = oid_get_user($canonical);

			if ($other) {
				if ($other->id == $cur->id) {
					$this->message(_('You already have this OpenID!'));
				} else {
					$this->message(_('Someone else already has this OpenID.'));
				}
				return;
			}

			# start a transaction

			$cur->query('BEGIN');

			$result = oid_link_user($cur->id, $canonical, $display);

			if (!$result) {
				$this->message(_('Error connecting user.'));
				return;
			}
			if ($sreg) {
				if (!oid_update_user($cur, $sreg)) {
					$this->message(_('Error updating profile'));
					return;
				}
			}

			# success!

			$cur->query('COMMIT');

			oid_set_last($display);

			common_redirect(common_local_url('openidsettings'));
		}
	}

	function message($msg) {
		common_show_header(_('OpenID Login'));
		common_element('p', NULL, $msg);
		common_show_footer();
	}
}
