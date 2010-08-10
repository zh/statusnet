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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$longoptions = array('dry-run', 'start=', 'end=');

$helptext = <<<END_OF_USERROLE_HELP
fixup_deletions.php [options]
Finds notices posted by deleted users and cleans them up.
Stray incompletely deleted items cause various fun problems!

     --dry-run  look but don't touch
     --start=N  start looking at profile_id N instead of 1
     --end=N    end looking at profile_id N instead of the max

END_OF_USERROLE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

/**
 * Find the highest profile_id currently listed in the notice table;
 * this field is indexed and should return very quickly.
 *
 * We check notice.profile_id rather than profile.id because we're
 * looking for notices left behind after deletion; if the most recent
 * accounts were deleted, we wouldn't have them from profile.
 *
 * @return int
 * @access private
 */
function get_max_profile_id()
{
    $query = 'SELECT MAX(profile_id) AS id FROM notice';

    $profile = new Profile();
    $profile->query($query);

    if ($profile->fetch()) {
        return intval($profile->id);
    } else {
        die("Something went awry; could not look up max used profile_id.");
    }
}

/**
 * Check for profiles in the given id range that are missing, presumed deleted.
 *
 * @param int $start beginning profile.id, inclusive
 * @param int $end final profile.id, inclusive
 * @return array of integer profile.ids
 * @access private
 */
function get_missing_profiles($start, $end)
{
    $query = sprintf("SELECT id FROM profile WHERE id BETWEEN %d AND %d",
                     $start, $end);

    $profile = new Profile();
    $profile->query($query);

    $all = range($start, $end);
    $known = array();
    while ($row = $profile->fetch()) {
        $known[] = intval($profile->id);
    }
    unset($profile);

    $missing = array_diff($all, $known);
    return $missing;
}

/**
 * Look for stray notices from this profile and, if present, kill them.
 *
 * @param int $profile_id
 * @param bool $dry if true, we won't delete anything
 */
function cleanup_missing_profile($profile_id, $dry)
{
    $notice = new Notice();
    $notice->profile_id = $profile_id;
    $notice->find();
    if ($notice->N == 0) {
        return;
    }

    $s = ($notice->N == 1) ? '' : 's';
    print "Deleted profile $profile_id has $notice->N stray notice$s:\n";

    while ($notice->fetch()) {
        print "  notice $notice->id";
        if ($dry) {
            print " (skipped; dry run)\n";
        } else {
            $victim = clone($notice);
            try {
                $victim->delete();
                print " (deleted)\n";
            } catch (Exception $e) {
                print " FAILED: ";
                print $e->getMessage();
                print "\n";
            }
        }
    }
}

$dry = have_option('dry-run');

$max_profile_id = get_max_profile_id();
$chunk = 1000;

if (have_option('start')) {
    $begin = intval(get_option_value('start'));
} else {
    $begin = 1;
}
if (have_option('end')) {
    $final = min($max_profile_id, intval(get_option_value('end')));
} else {
    $final = $max_profile_id;
}

if ($begin < 1) {
    die("Silly human, you can't begin before profile number 1!\n");
}
if ($final < $begin) {
    die("Silly human, you can't end at $final if it's before $begin!\n");
}

// Identify missing profiles...
for ($start = $begin; $start <= $final; $start += $chunk) {
    $end = min($start + $chunk - 1, $final);

    print "Checking for missing profiles between id $start and $end";
    if ($dry) {
        print " (dry run)";
    }
    print "...\n";
    $missing = get_missing_profiles($start, $end);

    foreach ($missing as $profile_id) {
        cleanup_missing_profile($profile_id, $dry);
    }
}

echo "done.\n";

