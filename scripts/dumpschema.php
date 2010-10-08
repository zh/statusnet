#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
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

$helptext = <<<END_OF_CHECKSCHEMA_HELP
Attempt to pull a schema definition for a given table.

END_OF_CHECKSCHEMA_HELP;

$longoptions = array('diff');
require_once INSTALLDIR.'/scripts/commandline.inc';

function indentOptions($indent)
{
    $cutoff = 3;
    if ($indent < $cutoff) {
        $space = $indent ? str_repeat(' ', $indent * 4) : '';
        $sep = ",";
        $lf = "\n";
        $endspace = "$lf" . ($indent ? str_repeat(' ', ($indent - 1) * 4) : '');
    } else {
        $space = '';
        $sep = ", ";
        $lf = '';
        $endspace = '';
    }
    if ($indent - 1 < $cutoff) {
    }
    return array($space, $sep, $lf, $endspace);
}

function prettyDumpArray($arr, $key=null, $indent=0)
{
    // hack
    if ($key == 'primary key') {
        $subIndent = $indent + 2;
    } else {
        $subIndent = $indent + 1;
    }

    list($space, $sep, $lf, $endspace) = indentOptions($indent);
    list($inspace, $insep, $inlf, $inendspace) = indentOptions($subIndent);

    print "{$space}";
    if (!is_numeric($key)) {
        print "'$key' => ";
    }
    if (is_array($arr)) {
        print "array({$inlf}";
        $n = 0;
        foreach ($arr as $key => $row) {
            $n++;
            prettyDumpArray($row, $key, $subIndent);
            if ($n < count($arr)) {
                print "$insep$inlf";
            }
        }
        // hack!
        print "{$inendspace})";
    } else {
        print var_export($arr, true);
    }
}

function getCoreSchema($tableName)
{
    $schema = array();
    include INSTALLDIR . '/db/core.php';
    return $schema[$tableName];
}

function dumpTable($tableName, $live)
{
    if ($live) {
        $schema = Schema::get();
        $def = $schema->getTableDef($tableName);
    } else {
        // hack
        $def = getCoreSchema($tableName);
    }
    prettyDumpArray($def, $tableName);
    echo "\n";
}

function showDiff($a, $b)
{
    $fnameA = tempnam(sys_get_temp_dir(), 'defined-diff-a');
    file_put_contents($fnameA, $a);

    $fnameB = tempnam(sys_get_temp_dir(), 'detected-diff-b');
    file_put_contents($fnameB, $b);

    $cmd = sprintf('diff -U 100 %s %s',
            escapeshellarg($fnameA),
            escapeshellarg($fnameB));
    passthru($cmd);

    unlink($fnameA);
    unlink($fnameB);
}

if (count($args)) {
    foreach ($args as $tableName) {
        if (have_option('diff')) {
            ob_start();
            dumpTable($tableName, false);
            $defined = ob_get_clean();

            ob_start();
            dumpTable($tableName, true);
            $detected = ob_get_clean();

            showDiff($defined, $detected);
        } else {
            dumpTable($tableName, true);
        }
    }
} else {
    show_help($helptext);
}