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

class FinishimmediateAction extends Action {

	function handle($args) {
		parent::handle($args);
		
		$consumer = oid_consumer();

		$response = $consumer->complete(common_local_url('finishimmediate'));

		if ($response->status == Auth_OpenID_SUCCESS) {
			$display = $response->getDisplayIdentifier();
			$canonical = ($response->endpoint->canonicalID) ?
			  $response->endpoint->canonicalID : $response->getDisplayIdentifier();

			$user = $this->get_user($canonical);
			
			if ($user) {
				$this->update_user($user, $sreg);
				common_set_user($user->nickname);
				$this->go_backto();
				return;
			}
		}

		# Failure! Clear openid so we don't try it again
		
		oid_clear_last();
		$this->go_backto();
		return;
	}
	
	function go_backto() {
		common_ensure_session();
		$backto = $_SESSION['openid_immediate_backto'];
		if (!$backto) {
			# gar. Well, push them to the public page
			$backto = common_local_url('public');
		}
		common_redirect($backto);
	}
}
