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

$shortoptions = 'i:n:r:d';
$longoptions = array('id=', 'nickname=', 'role=', 'delete');

$helptext = <<<END_OF_USERROLE_HELP
userrole.php [options]
modifies a role for the given user

  -d --delete   delete the role
  -i --id       ID of the user
  -n --nickname nickname of the user
  -r --role     role to add (or delete)

END_OF_USERROLE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
    $user = User::staticGet('id', $id);
    if (empty($user)) {
        print "Can't find user with ID $id\n";
        exit(1);
    }
} else if (have_option('n', 'nickname')) {
    $nickname = get_option_value('n', 'nickname');
    $user = User::staticGet('nickname', $nickname);
    if (empty($user)) {
        print "Can't find user with nickname '$nickname'\n";
        exit(1);
    }
} else {
    print "You must provide either an ID or a nickname.\n";
    exit(1);
}

$role = get_option_value('r', 'role');

if (empty($role)) {
    print "You must provide a role.\n";
    exit(1);
}

if (have_option('d', 'delete')) {
    print "Revoking role '$role' from user '$user->nickname' ($user->id)...";
    try {
        $user->revokeRole($role);
        print "OK\n";
    } catch (Exception $e) {
        print "FAIL\n";
        print $e->getMessage();
        print "\n";
    }
} else {
    print "Granting role '$role' to user '$user->nickname' ($user->id)...";
    try {
        $user->grantRole($role);
        print "OK\n";
    } catch (Exception $e) {
        print "FAIL\n";
        print $e->getMessage();
        print "\n";
    }
}
