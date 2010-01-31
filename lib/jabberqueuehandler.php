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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Queue handler for pushing new notices to Jabber users.
 * @fixme this exception handling doesn't look very good.
 */
class JabberQueueHandler extends QueueHandler
{
    var $conn = null;

    function transport()
    {
        return 'jabber';
    }

    function handle($notice)
    {
        require_once(INSTALLDIR.'/lib/jabber.php');
        try {
            return jabber_broadcast_notice($notice);
        } catch (XMPPHP_Exception $e) {
            common_log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
            return false;
        }
    }
}
