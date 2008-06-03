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

class UserauthorizationAction extends Action {
	function handle($args) {
		parent::handle($args);
		
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->send_authorization();
		} else {
			try {
				common_debug('userauthorization.php - fetching request');
				$req = $this->get_request();
				if (!$req) {
					common_server_error(_t('Cannot find request'));
				}
				common_debug('userauthorization.php - $req = "'.print_r($req,TRUE).'"');
				$server = omb_oauth_server();
				common_debug('userauthorization.php - checking request version');
				$server->get_version($req);
				common_debug('userauthorization.php - getting the consumer');
				$consumer = $server->get_consumer($req);
				common_debug('userauthorization.php - $consumer = "'.print_r($consumer,TRUE).'"');
				$token = $server->get_token($req, $consumer, "request");
				common_debug('userauthorization.php - $token = "'.print_r($token,TRUE).'"');
				$server->check_signature($req, $consumer, $token);
			} catch (OAuthException $e) {
				$this->clear_request();
				common_server_error($e->getMessage());
				return;
			}
			
			if (common_logged_in()) {
				$this->show_form($req);
			} else {
				# Go log in, and then come back
				common_set_returnto(common_local_url('userauthorization'));
				common_redirect(common_local_url('login'));
			}
		}
	}
	
	function store_request($req) {
		common_ensure_session();
		$_SESSION['userauthorizationrequest'] = $req;
	}
	
	function get_request() {
		common_ensure_session();		
		$req = $_SESSION['userauthorizationrequest'];
		if (!$req) {
			# XXX: may have an uncaught exception
			$req = OAuthRequest::from_request();
			if ($req) {
				$this->store_request($req);
			}
		}
		return $req;
	}
	
	function show_form($req) {
		common_show_header(_t('Authorize subscription'));

		common_show_footer();
	}
	
	function send_authorization() {
		$req = $this->get_request();
		
		if (!$req) {
			common_user_error(_t('No authorization request!'));
			return;
		}
		
		if ($this->boolean('authorize')) {
			
		}
	}
}
