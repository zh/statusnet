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

common_log(LOG_INFO, 'Initializing conversation table...');

$notice = new Notice();
$notice->query('select distinct conversation from notice');

while ($notice->fetch()) {
    $id = $notice->conversation;

    if ($id) {
        $uri = common_local_url('conversation', array('id' => $id));

        // @fixme db_dataobject won't save our value for an autoincrement
        // so we're bypassing the insert wrappers
        $conv = new Conversation();
        $sql = "insert into conversation (id,uri,created) values(%d,'%s','%s')";
        $sql = sprintf($sql,
                       $id,
                       $conv->escape($uri),
                       $conv->escape(common_sql_now()));
        echo "$id ";
        $conv->query($sql);
        print "... ";
    }
}
print "done.\n";
