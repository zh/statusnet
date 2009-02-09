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

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/lib/facebookutil.php';

// For storing the last run date-time
$last_updated_file = INSTALLDIR . '/scripts/facebook_last_updated';

// Lock file name
$lock_file = INSTALLDIR . '/scripts/update_facebook.lock';

// Make sure only one copy of the script is running at a time
$lock_file = @fopen($lock_file, "w+");
if (!flock( $lock_file, LOCK_EX | LOCK_NB, &$wouldblock) || $wouldblock) {
    die("Can't open lock file. Script already running?\n");
}

$facebook = getFacebook();
$current_time = time();
$since = getLastUpdated();
updateLastUpdated($current_time);
$notice = getFacebookNotices($since);
$cnt = 0;

while($notice->fetch()) {

    $flink = Foreign_link::getByUserID($notice->profile_id, FACEBOOK_SERVICE);
    $user = $flink->getUser();
    $fbuid = $flink->foreign_id;
    
    if (!userCanUpdate($fbuid)) {
        continue;
    }

    $prefix = $facebook->api_client->data_getUserPreference(FACEBOOK_NOTICE_PREFIX, $fbuid);
    $content = "$prefix $notice->content";
    
    if (($flink->noticesync & FOREIGN_NOTICE_SEND) == FOREIGN_NOTICE_SEND) {

        // If it's not a reply, or if the user WANTS to send replies...
        if (!preg_match('/@[a-zA-Z0-9_]{1,15}\b/u', $content) ||
            (($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) == FOREIGN_NOTICE_SEND_REPLY)) {
             
                // Avoid a Loop
                if ($notice->source != 'Facebook') {
                    
                    try {
                        $facebook->api_client->users_setStatus($content, 
                            $fbuid, false, true);
                        updateProfileBox($facebook, $flink, $notice);
                        $cnt++;
                    } catch(FacebookRestClientException $e) {
                        print "Couldn't sent notice $notice->id!\n";
                        print $e->getMessage();

                        // Remove flink?
                    }
                }
        }
    }
}

if ($cnt > 0) {
    print date('r', $current_time) . 
    ": Found $cnt new notices for Facebook since last run at " . 
     date('r', $since) . "\n";
}

fclose($lock_file);
exit(0);


function userCanUpdate($fbuid) {
    
    global $facebook;

    $result = false;
    
    try {
        $result = $facebook->api_client->users_hasAppPermission('status_update', $fbuid);
    } catch(FacebookRestClientException $e){
        print_r($e);
    }

    return $result;
}

function getLastUpdated(){
    global $last_updated_file, $current_time;
    $last = $current_time;

    if (file_exists($last_updated_file) && 
        ($file = fopen($last_updated_file, 'r'))) {
            $last = fgets($file);
    } else {
        print "$last_updated_file doesn't exit. Trying to create it...\n";
        $file = fopen($last_updated_file, 'w+') or 
            die("Can't open $last_updated_file for writing!\n");
        print 'Success. Using current time (' . date('r', $last) . 
            ") to look for new notices.\n";
    }
    
    fclose($file);
    return $last;
}

function updateLastUpdated($time){
    global $last_updated_file;
    $file = fopen($last_updated_file, 'w') or
        die("Can't open $last_updated_file for writing!");
    fwrite($file, $time);
    fclose($file);
}

