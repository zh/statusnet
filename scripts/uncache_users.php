#!/usr/bin/env php
<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Control Yourself, Inc.
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

# Abort if called from a web server

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');

$id_file = ($argc > 1) ? $argv[1] : 'ids.txt';

common_log(LOG_INFO, 'Updating user inboxes.');

$ids = file($id_file);

foreach ($ids as $id) {
	
	$user = User::staticGet('id', $id);

	if (!$user) {
		common_log(LOG_WARNING, 'No such user: ' . $id);
		continue;
	}

    $user->decache();
    
    $memc = common_memcache();
    
    $memc->delete(common_cache_key('user:notices_with_friends:'. $user->id));
    $memc->delete(common_cache_key('user:notices_with_friends:'. $user->id . ';last'));
}
