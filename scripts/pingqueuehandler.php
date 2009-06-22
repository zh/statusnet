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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i::';
$longoptions = array('id::');

$helptext = <<<END_OF_PING_HELP
Daemon script for pushing new notices to ping servers.

    -i --id           Identity (default none)

END_OF_PING_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once INSTALLDIR . '/lib/ping.php';
require_once INSTALLDIR . '/lib/queuehandler.php';

class PingQueueHandler extends QueueHandler {

	function transport() {
		return 'ping';
	}

	function start() {
		$this->log(LOG_INFO, "INITIALIZE");
		return true;
	}

	function handle_notice($notice) {
		return ping_broadcast_notice($notice);
	}

	function finish() {
	}
}

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$handler = new PingQueueHandler($id);

$handler->runOnce();
