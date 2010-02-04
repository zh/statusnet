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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all');

$helptext = <<<END_OF_UPDATEPROFILEURL_HELP
updateprofileurl.php [options]
update the URLs of all avatars in the system

  -i --id       ID of user to update
  -n --nickname nickname of the user to update
  -a --all      update all

END_OF_UPDATEPROFILEURL_HELP;

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
                updateProfileURL($user);
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

function updateProfileURL($user)
{
    $profile = $user->getProfile();

    if (empty($profile)) {
        throw new Exception("Can't find profile for user $user->nickname ($user->id)");
    }

    $orig = clone($profile);

    $profile->profileurl = common_profile_url($user->nickname);

    if (!have_option('q', 'quiet')) {
        print "Updating profile url for $user->nickname ($user->id) ".
          "from $orig->profileurl to $profile->profileurl...";
    }

    $result = $profile->update($orig);

    if (!$result) {
        print "FAIL.\n";
        common_log_db_error($profile, 'UPDATE', __FILE__);
        throw new Exception("Can't update profile for user $user->nickname ($user->id)");
    }

    common_broadcast_profile($profile);

    print "OK.\n";
}
