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

define('INSTALLDIR', dirname(__FILE__));
define('LACONICA', true);

require_once(INSTALLDIR . "/lib/common.php");

# get and cache current user

$user = common_current_user();

# initialize language env

common_init_language();

$action = $_REQUEST['action'];

if (!$action || !preg_match('/^[a-zA-Z0-9_-]*$/', $action)) {
    common_redirect(common_local_url('public'));
}

$actionfile = INSTALLDIR."/actions/$action.php";

if (file_exists($actionfile)) {
    require_once($actionfile);
    $action_class = ucfirst($action)."Action";
    $action_obj = new $action_class();
	if ($config['db']['mirror'] && $action_obj->is_readonly()) {
		if (is_array($config['db']['mirror'])) {
			# "load balancing", ha ha
			$k = array_rand($config['db']['mirror']);
			$mirror = $config['db']['mirror'][$k];
		} else {
			$mirror = $config['db']['mirror'];
		}
		$config['db']['database'] = $mirror;
	}
    if (call_user_func(array($action_obj, 'prepare'), $_REQUEST)) {
		call_user_func(array($action_obj, 'handle'), $_REQUEST);
	}
} else {
    common_user_error(_('Unknown action'));
}