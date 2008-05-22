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

define('OPENMICROBLOGGING01', 'http://openmicroblogging.org/protocol/0.1');

class XrdsAction extends Action {

	function handle($args) {
		parent::handle($args);
		$nickname = $this->trimmed('nickname');
		$user = User::staticGet('nickname', $nickname);
		if (!$user) {
			common_user_error(_t('No such user.'));
			return;
		}
		$this->show_xrds($user);
	}

	function show_xrds($user) {
		
		header('Content-Type: application/rdf+xml');

		common_start_xml();
		common_element_start('xrds:XRDS', array('xmlns:xrds' => 'xri://$xrds',
												'xmlns' => 'xri://$xrd*($v*2.0)'));
		common_element_start('XRD');

		$this->show_service(OPENMICROBLOGGING01.'/identifier',
							$user->uri);

		# XXX: decide whether to include user's ID/nickname in postNotice URL
		
		foreach (array('requestToken', 'userAuthorization',
					   'accessToken', 'postNotice',
					   'updateProfile') as $type) {
			$this->show_service(OPENMICROBLOGGING01.'/'.$type,
								common_local_url(strtolower($type)));
		}
		
		common_element_end('XRD');
		common_element_end('xrds:XRDS');
		common_end_xml();
	}
	
	function show_service($type, $uri) {
		common_element_start('Service');
		common_element('Type', $type);
		common_element('URI', $uri);
		common_element_end('Service');
	}
}