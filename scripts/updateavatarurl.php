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

$helptext = <<<END_OF_UPDATEAVATARURL_HELP
updateavatarurl.php [options]
update the URLs of all avatars in the system

  -i --id       ID of user to update
  -n --nickname nickname of the user to update
  -a --all      update all

END_OF_UPDATEAVATARURL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::staticGet('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
        updateAvatars($user);
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::staticGet('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'");
        }
        updateAvatars($user);
    } else if (have_option('a', 'all')) {
        $user = new User();
        if ($user->find()) {
            while ($user->fetch()) {
                updateAvatars($user);
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

function updateAvatars($user)
{
    $touched = false;

    if (!have_option('q', 'quiet')) {
        print "Updating avatars for user '".$user->nickname."' (".$user->id.")...";
    }

    $avatar = new Avatar();

    $avatar->profile_id = $user->id;

    if (!$avatar->find()) {
        if (have_option('v', 'verbose')) {
                print "(none found)...";
        }
    } else {
        while ($avatar->fetch()) {
            if (have_option('v', 'verbose')) {
                if ($avatar->original) {
                    print "original...";
                } else {
                    print $avatar->width."...";
                }
            }

            $orig_url = $avatar->url;

            $avatar->url = Avatar::url($avatar->filename);

            if ($avatar->url != $orig_url) {
                $sql =
                  "UPDATE avatar SET url = '" . $avatar->url . "' ".
                  "WHERE profile_id = " . $avatar->profile_id . " ".
                  "AND width = " . $avatar->width . " " .
                  "AND height = " . $avatar->height . " ";

                if ($avatar->original) {
                    $sql .= "AND original = 1 ";
                }

                if (!$avatar->query($sql)) {
                    throw new Exception("Can't update avatar for user " . $user->nickname . ".");
                } else {
                    $touched = true;
                }
            }
        }
    }

    if ($touched) {
        $profile = $user->getProfile();
        common_broadcast_profile($profile);
    }

    if (have_option('v', 'verbose')) {
        print "DONE.";
    }
    if (!have_option('q', 'quiet') || have_option('v', 'verbose')) {
        print "\n";
    }
}
