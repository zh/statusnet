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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapifavoritesAction extends TwitterapiAction {

	function is_readonly() {
		
		static $write_methods = array('favorites');
		
		$cmdtext = explode('.', $this->arg('method'));		
		
		if (in_array($cmdtext[0], $write_methods)) {			
			return false;
		}
				
		return true;
	}

	function favorites($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}

	function create($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}
	
	function destroy($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}	

}