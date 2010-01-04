#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'u::';
$longoptions = array('start-user-id=', 'sleep-time=');

$helptext = <<<END_OF_TRIM_HELP
Batch script for trimming notice inboxes to a reasonable size.

    -u <id>
    --start-user-id=<id>   User ID to start after. Default is all.
    --sleep-time=<integer> Amount of time to wait (in seconds) between trims. Default is zero.

END_OF_TRIM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$id = null;
$sleep_time = 0;

if (have_option('u')) {
    $id = get_option_value('u');
} else if (have_option('--start-user-id')) {
    $id = get_option_value('--start-user-id');
} else {
    $id = null;
}

if (have_option('--sleep-time')) {
    $sleep_time = intval(get_option_value('--sleep-time'));
}

$quiet = have_option('q') || have_option('--quiet');

$user = new User();

if (!empty($id)) {
    $user->whereAdd('id > ' . $id);
}

$cnt = $user->find();

while ($user->fetch()) {
    if (!$quiet) {
        print "Trimming inbox for user $user->id";
    }
    $count = Notice_inbox::gc($user->id);
    if ($count) {
        if (!$quiet) {
            print ": $count trimmed...";
        }
        sleep($sleep_time);
    }
    if (!$quiet) {
        print "\n";
    }
}
