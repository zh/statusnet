#!/usr/bin/env php
<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2009, Control Yourself, Inc.
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

$shortoptions = 'u::';
$longoptions = array('start-user-id::');

$helptext = <<<END_OF_TRIM_HELP
Batch script for trimming notice inboxes to a reasonable size.

    -u <id>
    --start-user-id=<id>   User ID to start after. Default is all.

END_OF_TRIM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$id = null;

if (have_option('u')) {
    $id = get_option_value('u');
} else if (have_option('--start-user-id')) {
    $id = get_option_value('--start-user-id');
} else {
    $id = null;
}

$user = new User();

if (!empty($id)) {
    $user->whereAdd('id > ' . $id);
}

$cnt = $user->find();

while ($user->fetch()) {
    Notice_inbox::gc($user->id);
}
