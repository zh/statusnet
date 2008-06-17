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

require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/MySQLStore.php');

function oid_store() {
    static $store = NULL;
	if (!$store) {
		# Can't be called statically
		$user = new User();
		$conn = $user->getDatabaseConnection();
		$store = new Auth_OpenID_MySQLStore($conn);
	}
	return $store;
}

function oid_consumer() {
	$store = oid_store();
	$consumer = new Auth_OpenID_Consumer($store);
	return $consumer;
}