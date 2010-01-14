#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
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

$shortoptions = 'i:n:af:';
$longoptions = array('id=', 'nickname=', 'all', 'file=');

$helptext = <<<END_OF_INITIALIZEINBOX_HELP
initializeinbox.php [options]
initialize the inbox for a user

  -i --id         ID of user to update
  -n --nickname   nickname of the user to update
  -f FILENAME     read list of IDs from FILENAME (1 per line)
  --file=FILENAME ditto
  -a --all        update all

END_OF_INITIALIZEINBOX_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::staticGet('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
        initializeInbox($user);
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::staticGet('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'");
        }
        initializeInbox($user);
    } else if (have_option('a', 'all')) {
        $user = new User();
        if ($user->find()) {
            while ($user->fetch()) {
                initializeInbox($user);
            }
        }
    } else if (have_option('f', 'file')) {
        $filename = get_option_value('f', 'file');
        if (!file_exists($filename)) {
            throw new Exception("No such file '$filename'.");
        } else if (!is_readable($filename)) {
            throw new Exception("Can't read '$filename'.");
        }
        $ids = file($filename);
        foreach ($ids as $id) {
            $user = User::staticGet('id', $id);
            if (empty($user)) {
                print "Can't find user with id '$id'.\n";
            }
            initializeInbox($user);
        }
    } else {
        show_help();
        exit(1);
    }
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}

function initializeInbox($user)
{
    if (!have_option('q', 'quiet')) {
        print "Initializing inbox for $user->nickname...";
    }

    $inbox = Inbox::staticGet('user_id', $user->id);

    if ($inbox && !empty($inbox->fake)) {
        if (!have_option('q', 'quiet')) {
            echo "(replacing faux cached inbox)";
        }
        $inbox = false;
    }
    if (!empty($inbox)) {
        if (!have_option('q', 'quiet')) {
            print "SKIP\n";
        }
    } else {
        $inbox = Inbox::initialize($user->id);
        if (!have_option('q', 'quiet')) {
            if (empty($inbox)) {
                print "ERR\n";
            } else {
                print "DONE\n";
            }
        }
    }
}
