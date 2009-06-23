#!/usr/bin/env php
<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

// Uncomment this to get useful console output

$helptext = <<<END_OF_TWITTER_HELP
Batch script for synching local friends with Twitter friends.

END_OF_TWITTER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

// Make a lockfile
$lockfilename = lockFilename();
if (!($lockfile = @fopen($lockfilename, "w"))) {
    print "Already running... exiting.\n";
    exit(1);
}

// Obtain an exlcusive lock on file (will fail if script is already going)
if (!@flock( $lockfile, LOCK_EX | LOCK_NB, &$wouldblock) || $wouldblock) {
    // Script already running - abort
    @fclose($lockfile);
    print "Already running... exiting.\n";
    exit(1);
}

$flink = new Foreign_link();
$flink->service = 1; // Twitter
$flink->orderBy('last_friendsync');
$flink->limit(25);  // sync this many users during this run
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

        save_twitter_friends($user, $fuser->id, $fuser->nickname, $flink->credentials);

        $flink->last_friendsync = common_sql_now();
        $flink->update();

        if (defined('SCRIPT_DEBUG')) {
            print "\nDONE\n";
        } else {
            print "DONE\n";
        }
    }
}

function lockFilename()
{
    $piddir = common_config('daemon', 'piddir');
    if (!$piddir) {
        $piddir = '/var/run';
    }

    return $piddir . '/synctwitterfriends.lock';
}

// Cleanup
fclose($lockfile);
unlink($lockfilename);

exit(0);
