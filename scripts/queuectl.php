#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * Sends control signals to running queue daemons.
 *
 * @author Brion Vibber <brion@status.net>
 * @package QueueHandler
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'ur';
$longoptions = array('update', 'restart', 'stop');

$helptext = <<<END_OF_QUEUECTL_HELP
Send broadcast events to control any running queue handlers.
(Currently for Stomp queues only.)

Events relating to current site (as selected with -s etc)
    -u --update       Announce new site or updated configuration. Running
                      daemons will start subscribing to any new queues needed
                      for this site.

Global events:
    -r --restart      Graceful restart of all threads
       --stop         Graceful shutdown of all threads

END_OF_QUEUECTL_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function doSendControl($message, $event, $param='')
{
    print $message;
    $qm = QueueManager::get();
    if ($qm->sendControlSignal($event, $param)) {
        print " sent.\n";
    } else {
        print " FAILED.\n";
    }
}

$actions = 0;

if (have_option('u') || have_option('--update')) {
    $nickname = common_config('site', 'nickname');
    doSendControl("Sending site update signal to queue daemons for $nickname",
                  "update", $nickname);
    $actions++;
}

if (have_option('r') || have_option('--restart')) {
    doSendControl("Sending graceful restart signal to queue daemons...",
                  "restart");
    $actions++;
}

if (have_option('--stop')) {
    doSendControl("Sending graceful shutdown signal to queue daemons...",
                  "shutdown");
    $actions++;
}

if (!$actions) {
    show_help();
}

