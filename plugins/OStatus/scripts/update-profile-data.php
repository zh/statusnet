#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

$helptext = <<<END_OF_HELP
update-profile-data.php [options] http://example.com/profile/url

Rerun profile discovery for the given OStatus remote profile, and save the
updated profile data (nickname, avatar, bio, etc). Doesn't touch feed state.
Can be used to clean up after breakages.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

if (empty($args[0]) || !Validate::uri($args[0])) {
    print "$helptext";
    exit(1);
}

$uri = $args[0];


$oprofile = Ostatus_profile::staticGet('uri', $uri);

if (!$oprofile) {
    print "No OStatus remote profile known for URI $uri\n";
    exit(1);
}

function showProfileInfo($oprofile) {
    if ($oprofile->isGroup()) {
        echo "group\n";
    } else {
        $profile = $oprofile->localProfile();
        foreach (array('nickname', 'bio', 'homepage', 'location') as $field) {
            print "  $field: {$profile->$field}\n";
        }
    }
    echo "\n";
}

echo "Before:\n";
showProfileInfo($oprofile);

$feedurl = $oprofile->feeduri;
$client = new HttpClient();
$response = $client->get($feedurl);
if ($response->isOk()) {
    echo "Updating profile from feed: $feedurl\n";
    $dom = new DOMDocument();
    if ($dom->loadXML($response->getBody())) {
        $feed = $dom->documentElement;
        $entries = $dom->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if ($entries->length) {
            $entry = $entries->item(0);
            $activity = new Activity($entry, $feed);
            $oprofile->checkAuthorship($activity);
            echo "  (ok)\n";
        } else {
            echo "  (no entry; skipping)\n";
        }
    } else {
        echo "  (bad feed; skipping)\n";
    }
} else {
    echo "Failed feed fetch: {$response->getStatus()} for $feedurl\n";
}

echo "After:\n";
showProfileInfo($oprofile);
