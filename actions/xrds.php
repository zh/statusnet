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

class XrdsAction extends Action {

	function is_readonly() {				
		return true;
	}

	function handle($args) {
		parent::handle($args);
		$nickname = $this->trimmed('nickname');
		$user = User::staticGet('nickname', $nickname);
		if (!$user) {
			common_user_error(_('No such user.'));
			return;
		}
		$this->show_xrds($user);
	}

	function show_xrds($user) {

		header('Content-Type: application/xrds+xml');

		common_start_xml();
		common_element_start('XRDS', array('xmlns' => 'xri://$xrds'));

		common_element_start('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
		                                  'xml:id' => 'oauth',
										  'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
										  'version' => '2.0'));

		common_element('Type', NULL, 'xri://$xrds*simple');

		$this->show_service(OAUTH_ENDPOINT_REQUEST,
							common_local_url('requesttoken'),
							array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
							array(OAUTH_HMAC_SHA1),
							$user->uri);

		$this->show_service(OAUTH_ENDPOINT_AUTHORIZE,
							common_local_url('userauthorization'),
							array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
							array(OAUTH_HMAC_SHA1));

		$this->show_service(OAUTH_ENDPOINT_ACCESS,
							common_local_url('accesstoken'),
							array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
							array(OAUTH_HMAC_SHA1));

		$this->show_service(OAUTH_ENDPOINT_RESOURCE,
							NULL,
							array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
							array(OAUTH_HMAC_SHA1));

		common_element_end('XRD');

		# XXX: decide whether to include user's ID/nickname in postNotice URL

		common_element_start('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
		                                  'xml:id' => 'omb',
										  'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
										  'version' => '2.0'));

		common_element('Type', NULL, 'xri://$xrds*simple');

		$this->show_service(OMB_ENDPOINT_POSTNOTICE,
							common_local_url('postnotice'));

		$this->show_service(OMB_ENDPOINT_UPDATEPROFILE,
							common_local_url('updateprofile'));

		common_element_end('XRD');

		common_element_start('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
										  'version' => '2.0'));

		common_element('Type', NULL, 'xri://$xrds*simple');

		$this->show_service(OAUTH_DISCOVERY,
							'#oauth');
		$this->show_service(OMB_NAMESPACE,
							'#omb');

		common_element_end('XRD');

		common_element_end('XRDS');
		common_end_xml();
	}

	function show_service($type, $uri, $params=NULL, $sigs=NULL, $localId=NULL) {
		common_element_start('Service');
		if ($uri) {
			common_element('URI', NULL, $uri);
		}
		common_element('Type', NULL, $type);
		if ($params) {
			foreach ($params as $param) {
				common_element('Type', NULL, $param);
			}
		}
		if ($sigs) {
			foreach ($sigs as $sig) {
				common_element('Type', NULL, $sig);
			}
		}
		if ($localId) {
			common_element('LocalID', NULL, $localId);
		}
		common_element_end('Service');
	}
}