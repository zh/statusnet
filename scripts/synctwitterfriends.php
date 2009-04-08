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

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

// Set this to true to get useful console output
define('SCRIPT_DEBUG', false);

require_once(INSTALLDIR . '/lib/common.php');

$flink = new Foreign_link();
$flink->service = 1; // Twitter
$cnt = $flink->find();

print "Updating Twitter friends subscriptions for $cnt users.\n";

while ($flink->fetch()) {

    if (($flink->friendsync & FOREIGN_FRIEND_RECV) == FOREIGN_FRIEND_RECV) {

        $user = User::staticGet($flink->user_id);

        if (empty($user)) {
            common_log(LOG_WARNING, "Unmatched user for ID " . $flink->user_id);
            print "Unmatched user for ID $flink->user_id\n";
            continue;
        }

        print "Updating Twitter friends for $user->nickname (Laconica ID: $user->id)... ";

        $fuser = $flink->getForeignUser();

        if (empty($fuser)) {
            common_log(LOG_WARNING, "Unmatched user for ID " . $flink->user_id);
            print "Unmatched user for ID $flink->user_id\n";
            continue;
        }

        $result = save_twitter_friends($user, $fuser->id,
                       $fuser->nickname, $flink->credentials);
        if (SCRIPT_DEBUG) {
            print "\nDONE\n";
        } else {
            print "DONE\n";
        }
    }
}

exit(0);
