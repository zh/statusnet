#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

$helptext = <<<ENDOFHELP
USAGE: decache.php <table> <id> [<column>]
Clears the cache for the object in table <table> with id <id>
If <column> is specified, use that instead of 'id'


ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (count($args) < 2 || count($args) > 3) {
    show_help();
}

$table = $args[0];
$id = $args[1];
if (count($args) > 2) {
    $column = $args[2];
} else {
    $column = 'id';
}

$object = Memcached_DataObject::staticGet($table, $column, $id);

if (!$object) {
    print "No such '$table' with $column = '$id'; it's possible some cache keys won't be cleared properly.\n";
    $class = ucfirst($table);
    $object = new $class();
    $object->column = $id;
}

$result = $object->decache();
