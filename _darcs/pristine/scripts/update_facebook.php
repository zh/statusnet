#!/usr/bin/env php
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/facebookutil.php');

// For storing the last run date-time
$last_updated_file = "/home/zach/laconica/scripts/facebook_last_updated";

// Lock file name
$tmp_file = "/tmp/update_facebook.lock";

// Make sure only one copy of the script is running at a time
if (!($tmp_file = @fopen($tmp_file, "w")))
{
	die("Can't open lock file. Script already running?");
}

$facebook = get_facebook();

$current_time = time();

$notice = get_facebook_notices(get_last_updated());

while($notice->fetch()) {

    $flink = Foreign_link::getByUserID($notice->profile_id, 2);
    $fbuid = $flink->foreign_id;

    update_status($fbuid, $notice);

}

update_last_updated($current_time);

exit(0);



function update_status($fbuid, $notice) {
    global $facebook;

    try {

        $result = $facebook->api_client->users_setStatus($notice->content, $fbuid, false, true);

    } catch(FacebookRestClientException $e){

    	print_r($e);
    }

}

function get_last_updated(){
	global $last_updated_file, $current_time;

	$file = fopen($last_updated_file, 'r');

	if ($file) {
	    $last = fgets($file);
	} else {
	    print "Unable to read $last_updated_file. Using current time.\n";
	    return $current_time;
	}

	fclose($file);

	return $last;
}

function update_last_updated($time){
	global $last_updated_file;
	$file = fopen($last_updated_file, 'w') or die("Can't open $last_updated_file for writing!");
	fwrite($file, $time);
	fclose($file);
}
