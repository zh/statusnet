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
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/jabber.php');
require_once(INSTALLDIR . '/lib/xmppqueuehandler.php');

set_error_handler('common_error_handler');

class JabberQueueHandler extends XmppQueueHandler
{

    var $conn = null;

    function transport()
    {
        return 'jabber';
    }

    function handle_notice($notice)
    {
        try {
            return jabber_broadcast_notice($notice);
        } catch (XMPPHP_Exception $e) {
            $this->log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
            exit(1);
        }
    }
}

// Abort immediately if xmpp is not enabled, otherwise the daemon chews up
// lots of CPU trying to connect to unconfigured servers
if (common_config('xmpp','enabled')==false) {
    print "Aborting daemon - xmpp is disabled\n";
    exit();
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

$resource = ($argc > 1) ? $argv[1] : (common_config('xmpp','resource') . '-queuehandler');

$handler = new JabberQueueHandler($resource);

$handler->runOnce();
