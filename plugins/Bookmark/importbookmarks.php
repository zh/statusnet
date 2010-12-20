<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_IMPORTBOOKMARKS_HELP
    importbookmarks.php [options]
    Restore a backed-up Delicious.com bookmark file

    -i --id       ID of user to import bookmarks for
    -n --nickname nickname of the user to import for
    -f --file     file to read from (STDIN by default)
END_OF_IMPORTBOOKMARKS_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function getBookmarksFile()
{
    $filename = get_option_value('f', 'file');

    if (empty($filename)) {
        show_help();
        exit(1);
    }

    if (!file_exists($filename)) {
        throw new Exception("No such file '$filename'.");
    }

    if (!is_file($filename)) {
        throw new Exception("Not a regular file: '$filename'.");
    }

    if (!is_readable($filename)) {
        throw new Exception("File '$filename' not readable.");
    }

    // TRANS: Commandline script output. %s is the filename that contains a backup for a user.
    printfv(_("Getting backup from file '%s'.")."\n",$filename);

    $html = file_get_contents($filename);

	return $html;
}

try {
	$dbi = new DeliciousBackupImporter();

	$user = getUser();

    $html = getBookmarksFile();

	$dbi->importBookmarks($user, $html);

} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
