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

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit(1);
}

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);
mb_internal_encoding('UTF-8');

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once('DB.php');

function fixup_utf8($max_id, $min_id) {

    $dbl = doConnect('latin1');

    if (empty($dbl)) {
        return;
    }

    $dbu = doConnect('utf8');

    if (empty($dbu)) {
        return;
    }

    // Do a separate DB connection

    $sth = $dbu->prepare("UPDATE notice SET content = UNHEX(?), rendered = UNHEX(?) WHERE id = ?");

    if (PEAR::isError($sth)) {
        echo "ERROR: " . $sth->getMessage() . "\n";
        return;
    }

    $sql = 'SELECT id, content, rendered FROM notice ' .
      'WHERE LENGTH(content) != CHAR_LENGTH(content)';

    if (!empty($max_id)) {
        $sql .= ' AND id <= ' . $max_id;
    }

    if (!empty($min_id)) {
        $sql .= ' AND id >= ' . $min_id;
    }

    $sql .= ' ORDER BY id DESC';

    $rn = $dbl->query($sql);

    if (PEAR::isError($rn)) {
        echo "ERROR: " . $rn->getMessage() . "\n";
        return;
    }

    echo "Number of rows: " . $rn->numRows() . "\n";

    $notice = array();

    while (DB_OK == $rn->fetchInto($notice)) {

        $id = ($notice[0])+0;
        $content = bin2hex($notice[1]);
        $rendered = bin2hex($notice[2]);

        echo "$id...";

        $result =& $dbu->execute($sth, array($content, $rendered, $id));

        if (PEAR::isError($result)) {
            echo "ERROR: " . $result->getMessage() . "\n";
            continue;
        }

        $cnt = $dbu->affectedRows();

        if ($cnt != 1) {
            echo "ERROR: 0 rows affected\n";
            continue;
        }

        $notice = Notice::staticGet('id', $id);
        $notice->decache();

        echo "OK\n";
    }
}

function doConnect($charset)
{
    $db = DB::connect(common_config('db', 'database'),
                      array('persistent' => false));

    if (PEAR::isError($db)) {
        echo "ERROR: " . $db->getMessage() . "\n";
        return NULL;
    }

//    $result = $db->query("SET NAMES $charset");

    $conn = $db->connection;

    $succ = mysqli_set_charset($conn, $charset);

    if (!$succ) {
        echo "ERROR: couldn't set charset\n";
        $db->disconnect();
        return NULL;
    }

    $result = $db->autoCommit(true);

    if (PEAR::isError($result)) {
        echo "ERROR: " . $result->getMessage() . "\n";
        $db->disconnect();
        return NULL;
    }

    return $db;
}

$max_id = ($argc > 1) ? $argv[1] : null;
$min_id = ($argc > 2) ? $argv[2] : null;

fixup_utf8($max_id, $min_id);
