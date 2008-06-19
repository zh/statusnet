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

$action = $_REQUEST['action'];

if (!$action) {
	common_redirect(common_local_url('public'));
}

# Do an OpenID immediate request if they're not logged in
# and they have an OpenID cookie

if (!common_logged_in() &&
	$_SERVER['REQUEST_METHOD'] == 'GET' &&
	$action != 'finishimmediate')
{
	require_once(INSTALLDIR.'/lib/openid.php');
	$openid_url = oid_get_last();
	if ($openid_url) {
		oid_check_immediate($openid_url);
		return;
	}
}

$actionfile = INSTALLDIR."/actions/$action.php";

if (file_exists($actionfile)) {
	require_once($actionfile);
	$action_class = ucfirst($action)."Action";
	$action_obj = new $action_class();
	call_user_func(array($action_obj, 'handle'), $_REQUEST);
} else {
	common_user_error(_t('Unknown action'));
}