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

class Action { // lawsuit

	var $args;

	function Action() {
	}

	function arg($key) {
		if (array_key_exists($key, $this->args)) {
			return $this->args[$key];
		} else {
			return NULL;
		}
	}

	function trimmed($key) {
		$arg = $this->arg($key);
		return (is_string($arg)) ? trim($arg) : $arg;
	}
	
	function handle($argarray) {
		$this->args =& common_copy_args($argarray);
	}
	
	function boolean($key, $def=false) {
		$arg = strtolower($this->trimmed($key));
		
		if (is_null($arg)) {
			return $def;
		} else if (in_array($arg, array('true', 'yes', '1'))) {
			return true;
		} else if (in_array($arg, array('false', 'no', '0'))) {
			return false;
		} else {
			return $def;
		}
	}
	
	function server_error($msg, $code=500) {
		$action = $this->trimmed('action');
		common_debug("Server error '$code' on '$action': $msg", __FILE__);
		common_server_error($msg, $code);
	}
	
	function client_error($msg, $code=400) {
		$action = $this->trimmed('action');
		common_debug("User error '$code' on '$action': $msg", __FILE__);
		common_user_error($msg, $code);
	}
}
