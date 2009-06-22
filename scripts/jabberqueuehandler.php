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

$shortoptions = 'r';
$longoptions = array('resource');

$helptext = <<<END_OF_JABBER_HELP
Daemon script for pushing new notices to Jabber users.

    -r --resource       Jabber Resource ID

END_OF_JABBER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/lib/jabber.php';
require_once INSTALLDIR . '/lib/xmppqueuehandler.php';

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

if (have_option('-r')) {
    $resource = get_option_value('-r');
} else if (have_option('--resource')) {
    $resource = get_option_value('--resource');
} else if (count($args) > 0) {
    $resource = $args[0];
} else {
    $resource = null;
}

$handler = new JabberQueueHandler($resource);

$handler->runOnce();
