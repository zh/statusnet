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

echo "Found $oprofile->N bogus ostatus_profile entries:\n";

while ($oprofile->fetch()) {
    echo "$oprofile->uri";

    if ($dry) {
        echo " (unchanged)\n";
    } else {
        echo " deleting...";
        $evil = clone($oprofile);
        $evil->delete();
        echo "  ok\n";
    }
}

echo "done.\n";

