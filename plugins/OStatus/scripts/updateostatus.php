#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all');

$helptext = <<<END_OF_UPDATEOSTATUS_HELP
updateprofileurl.php [options]
update the OMB subscriptions of a user to use OStatus if possible

  -i --id       ID of user to update
  -n --nickname nickname of the user to update
  -a --all      update all

END_OF_UPDATEOSTATUS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::staticGet('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
        updateProfileURL($user);
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::staticGet('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'");
        }
        updateProfileURL($user);
    } else if (have_option('a', 'all')) {
        $user = new User();
        if ($user->find()) {
            while ($user->fetch()) {
                updateOStatus($user);
            }
        }
    } else {
        show_help();
        exit(1);
    }
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

function updateOStatus($user)
{
    $up = $user->getProfile();

    $sp = $user->getSubscriptions();

    $rps = array();

    while ($sp->fetch()) {
        $remote = Remote_profile::staticGet('id', $sp->id);

        if (!empty($remote)) {
            $rps[] = clone($sp);
        }
    }

    foreach ($rps as $rp) {
        try {
            $op = Ostatus_profile::ensureProfile($rp->profileurl);

            if (!empty($op)) {
                Subscription::cancel($up, $rp);
                Subscription::start($up, $op->localProfile());
            }

        } catch (Exception $e) {
            common_log(LOG_WARNING, "Couldn't convert OMB subscription (" . $up->nickname . ", " . $rp->nickname .
                       ") to OStatus: " . $e->getMessage());
            continue;
        }
    }
}
