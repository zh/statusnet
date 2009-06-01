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

/**
 * Utility script to get a list of daemons that should run, based on the
 * current configuration. This is used by startdaemons.sh to determine what
 * it should and shouldn't start up. The output is a list of space-separated
 * daemon names.
 */

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');

if(common_config('xmpp','enabled')) {
    echo "xmppdaemon.php jabberqueuehandler.php publicqueuehandler.php ";
    echo "xmppconfirmhandler.php ";
}
echo "ombqueuehandler.php ";
echo "twitterqueuehandler.php ";
echo "facebookqueuehandler.php ";
echo "pingqueuehandler.php ";
echo "smsqueuehandler.php ";
