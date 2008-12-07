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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit(1);
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');

if ($argc != 3) {
    print "USAGE: setpassword.php <username> <password>\n";
    print "Sets the password of user with name <username> to <password>\n";
    exit(1);
}

$nickname = $argv[1];
$password = $argv[2];

if (mb_strlen($password) < 6) {
    print "Password must be 6 characters or more.\n";
    exit(1);
}

$user = User::staticGet('nickname', $nickname);

if (!$user) {
    print "No such user '$nickname'.\n";
    exit(1);
}

$original = clone($user);

$user->password = common_munge_password($password, $user->id);

if (!$user->update($original)) {
    print "Error updating user '$nickname'.\n";
    exit(1);
} else {
    print "Password for user '$nickname' updated.\n";
    exit(0);
}
