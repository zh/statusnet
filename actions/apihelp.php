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

# This naming convention looks real sick
class ApihelpAction extends Action {

	/* Returns the string "ok" in the requested format with a 200 OK HTTP status code.
	 * URL:http://identi.ca/api/help/test.format
	 * Formats: xml, json
	 */
	function test($args, $apidata) {
 		global $xw;
		if ($apidata['content-type'] == 'xml') {
			header('Content-Type: application/xml; charset=utf-8');		
			common_start_xml();
			common_element('ok', NULL, 'true');
			common_end_xml();
		} elseif ($apidata['content-type'] == 'json') {
			header('Content-Type: application/json; charset=utf-8');		
			print '"ok"';
		} else {
			common_user_error("API method not found!", $code=404);
		}
		exit();
	}

	function downtime_schedule($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
		exit();
	}
	
}