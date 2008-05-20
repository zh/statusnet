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

	function handle($argarray) {
		$this->args = array();
		foreach ($argarray as $k => $v) {
			$this->args[$k] = $v;
		}
	}
}
