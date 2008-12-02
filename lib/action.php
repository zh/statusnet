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

	# For initializing members of the class

	function init($argarray) {
		$this->args =& common_copy_args($argarray);
		return true;
	}

	# For comparison with If-Last-Modified
	# If not applicable, return NULL

	function last_modified() {
		return NULL;
	}

	function is_readonly() {
		return false;
	}

	function arg($key, $def=NULL) {
		if (array_key_exists($key, $this->args)) {
			return $this->args[$key];
		} else {
			return $def;
		}
	}

	function trimmed($key, $def=NULL) {
		$arg = $this->arg($key, $def);
		return (is_string($arg)) ? trim($arg) : $arg;
	}

	# Note: argarray ignored, since it's now passed in in init()

	function handle($argarray=NULL) {

		$lm = $this->last_modified();

		if ($lm) {
			header('Last-Modified: ' . date(DATE_RFC822, $lm));
			$if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
			if ($if_modified_since) {
				$ims = strtotime($if_modified_since);
				if ($lm <= $ims) {
					header('HTTP/1.1 304 Not Modified');
					# Better way to do this?
					exit(0);
				}
			}
		}
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

	function self_url() {
		$action = $this->trimmed('action');
		$args = $this->args;
		unset($args['action']);
		foreach (array_keys($_COOKIE) as $cookie) {
			unset($args[$cookie]);
		}
		return common_local_url($action, $args);
	}

	function nav_menu($menu) {
        $action = $this->trimmed('action');
        common_element_start('ul', array('id' => 'nav_views'));
        foreach ($menu as $menuaction => $menudesc) {
            common_menu_item(common_local_url($menuaction, isset($menudesc[2]) ? $menudesc[2] : NULL),
							 $menudesc[0],
							 $menudesc[1],
							 $action == $menuaction);
        }
        common_element_end('ul');
	}
}
