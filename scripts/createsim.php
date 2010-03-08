#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

$shortoptions = 'u:n:b:t:x:';
$longoptions = array('users=', 'notices=', 'subscriptions=', 'tags=', 'prefix=');

$helptext = <<<END_OF_CREATESIM_HELP
Creates a lot of test users and notices to (loosely) simulate a real server.

    -u --users         Number of users (default 100)
    -n --notices       Average notices per user (default 100)
    -b --subscriptions Average subscriptions per user (default no. users/20)
    -t --tags          Number of distinct hash tags (default 10000)
    -x --prefix        User name prefix (default 'testuser')

END_OF_CREATESIM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

// XXX: make these command-line options

function newUser($i)
{
    global $userprefix;
    $user = User::register(array('nickname' => sprintf('%s%d', $userprefix, $i),
                                 'password' => sprintf('password%d', $i),
                                 'fullname' => sprintf('Test User %d', $i)));
    if (!empty($user)) {
        $user->free();
    }
}

function newNotice($i, $tagmax)
{
    global $userprefix;

    $n = rand(0, $i - 1);
    $user = User::staticGet('nickname', sprintf('%s%d', $userprefix, $n));

    $is_reply = rand(0, 4);

    $content = 'Test notice content';

    if ($is_reply == 0) {
        $n = rand(0, $i - 1);
        $content = "@$userprefix$n " . $content;
    }

    $has_hash = rand(0, 2);

    if ($has_hash == 0) {
        $hashcount = rand(0, 2);
        for ($j = 0; $j < $hashcount; $j++) {
            $h = rand(0, $tagmax);
            $content .= " #tag{$h}";
        }
    }

    $notice = Notice::saveNew($user->id, $content, 'system');

    $user->free();
    $notice->free();
}

function newSub($i)
{
    global $userprefix;
    $f = rand(0, $i - 1);

    $fromnick = sprintf('%s%d', $userprefix, $f);

    $from = User::staticGet('nickname', $fromnick);

    if (empty($from)) {
        throw new Exception("Can't find user '$fromnick'.");
    }

    $t = rand(0, $i - 1);

    if ($t == $f) {
        $t++;
        if ($t > $i - 1) {
            $t = 0;
        }
    }

    $tunic = sprintf('%s%d', $userprefix, $t);

    $to = User::staticGet('nickname', $tunic);

    if (empty($to)) {
        throw new Exception("Can't find user '$tunic'.");
    }

    subs_subscribe_to($from, $to);

    $from->free();
    $to->free();
}

function main($usercount, $noticeavg, $subsavg, $tagmax)
{
    global $config;
    $config['site']['dupelimit'] = -1;

    $n = 1;

    newUser(0);

    // # registrations + # notices + # subs

    $events = $usercount + ($usercount * ($noticeavg + $subsavg));

    for ($i = 0; $i < $events; $i++)
    {
        $e = rand(0, 1 + $noticeavg + $subsavg);

        if ($e == 0) {
            newUser($n);
            $n++;
        } else if ($e < $noticeavg + 1) {
            newNotice($n, $tagmax);
        } else {
            newSub($n);
        }
    }
}

$usercount  = (have_option('u', 'users')) ? get_option_value('u', 'users') : 100;
$noticeavg  = (have_option('n', 'notices')) ? get_option_value('n', 'notices') : 100;
$subsavg    = (have_option('b', 'subscriptions')) ? get_option_value('b', 'subscriptions') : max($usercount/20, 10);
$tagmax     = (have_option('t', 'tags')) ? get_option_value('t', 'tags') : 10000;
$userprefix = (have_option('x', 'prefix')) ? get_option_value('x', 'prefix') : 'testuser';

main($usercount, $noticeavg, $subsavg, $tagmax);
