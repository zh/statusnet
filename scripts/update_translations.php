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

// Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');

// Master Laconica .pot file location (created by update_pot.sh)
$laconica_pot = INSTALLDIR . '/locale/laconica.po';

set_time_limit(60);

/* Languages to pull */
$languages = get_all_languages();

/* Update the languages */

foreach ($languages as $language) {

    $code = $language['lang'];
    $file_url = 'http://laconi.ca/pootle/' . $code .
        '/laconica/LC_MESSAGES/laconica.po';
    $lcdir = INSTALLDIR . '/locale/' . $code;
    $msgdir = "$lcdir/LC_MESSAGES";
    $pofile = "$msgdir/laconica.po";
    $mofile = "$msgdir/laconica.mo";

    /* Check for an existing */
    if (!is_dir($msgdir)) {
        mkdir($lcdir);
        mkdir($msgdir);
        $existingSHA1 = '';
    } else {
        $existingSHA1 = file_exists($pofile) ? sha1_file($pofile) : '';
    }

    /* Get the remote one */
    $new_file = curl_get_file($file_url);

    if ($new_file === FALSE) {
        echo "Couldn't retrieve .po file for $code: $file_url\n";
        continue;
    }

    // Update if the local .po file is different to the one downloaded, or
    // if the .mo file is not present.
    if (sha1($new_file) != $existingSHA1 || !file_exists($mofile)) {
        echo "Updating ".$code."\n";
        file_put_contents($pofile, $new_file);
        system(sprintf('msgmerge -U %s %s', $pofile, $laconica_pot));
        system(sprintf('msgfmt -f -o %s %s', $mofile, $pofile));
    } else {
        echo "Unchanged - ".$code."\n";
    }
}

echo "Finished\n";


function curl_get_file($url)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $url);
    $contents = curl_exec($c);
    curl_close($c);

    if (!empty($contents)) {
        return $contents;
    }

    return FALSE;
}
