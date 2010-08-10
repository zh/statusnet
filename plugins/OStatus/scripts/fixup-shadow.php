#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$longoptions = array('dry-run');

$helptext = <<<END_OF_USERROLE_HELP
fixup_shadow.php [options]
Patches up stray ostatus_profile entries with corrupted shadow entries
for local users and groups.

     --dry-run  look but don't touch

END_OF_USERROLE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$dry = have_option('dry-run');

$oprofile = new Ostatus_profile();

$marker = mt_rand(31337, 31337000);

$profileTemplate = common_local_url('userbyid', array('id' => $marker));
$encProfile = $oprofile->escape($profileTemplate, true);
$encProfile = str_replace($marker, '%', $encProfile);

$groupTemplate = common_local_url('groupbyid', array('id' => $marker));
$encGroup = $oprofile->escape($groupTemplate, true);
$encGroup = str_replace($marker, '%', $encGroup);

$sql = "SELECT * FROM ostatus_profile WHERE uri LIKE '%s' OR uri LIKE '%s'";
$oprofile->query(sprintf($sql, $encProfile, $encGroup));

$count = $oprofile->N;
echo "Found $count bogus ostatus_profile entries shadowing local users and groups:\n";

while ($oprofile->fetch()) {
    $uri = $oprofile->uri;
    if (preg_match('!/group/(\d+)/id!', $oprofile->uri, $matches)) {
        $id = intval($matches[1]);
        $group = Local_group::staticGet('group_id', $id);
        if ($group) {
            $nick = $group->nickname;
        } else {
            $nick = '<deleted>';
        }
        echo "group $id ($nick) hidden by $uri";
    } else if (preg_match('!/user/(\d+)!', $uri, $matches)) {
        $id = intval($matches[1]);
        $user = User::staticGet('id', $id);
        if ($user) {
            $nick = $user->nickname;
        } else {
            $nick = '<deleted>';
        }
        echo "user $id ($nick) hidden by $uri";
    } else {
        echo "$uri matched query, but we don't recognize it.\n";
        continue;
    }
    
    if ($dry) {
        echo " - skipping\n";
    } else {
        echo " - removing bogus ostatus_profile entry...";
        $evil = clone($oprofile);
        $evil->delete();
        echo "  ok\n";
    }
}

if ($count && $dry) {
    echo "NO CHANGES MADE -- To delete the bogus entries, run again without --dry-run option.\n";
} else {
    echo "done.\n";
}

