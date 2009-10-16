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

require_once INSTALLDIR.'/scripts/commandline.inc';

common_log(LOG_INFO, 'Fixing up conversations.');

$notice = new Notice();
$notice->query('select id, reply_to from notice where conversation is null');

while ($notice->fetch()) {

    $cid = null;
    
    $orig = clone($notice);
    
    if (empty($notice->reply_to)) {
        $notice->conversation = $notice->id;
    } else {
        $reply = Notice::staticGet('id', $notice->reply_to);

        if (empty($reply)) {
            common_log(LOG_WARNING, "Replied-to notice $notice->reply_to not found.");
            $notice->conversation = $notice->id;
        } else if (empty($reply->conversation)) {
            common_log(LOG_WARNING, "Replied-to notice $reply->id has no conversation ID.");
            $notice->conversation = $notice->id;
        } else {
            $notice->conversation = $reply->conversation;
        }
	
	unset($reply);
	$reply = null;
    }

    print "$notice->conversation";

    $result = $notice->update($orig);

    if (!$result) {
        common_log_db_error($notice, 'UPDATE', __FILE__);
        continue;
    }

    $orig = null;
    unset($orig);
    
    print ".\n";
}
