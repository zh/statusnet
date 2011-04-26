#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Script to print out current version of the software
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

$helptext = <<<END_OF_SITEFORDOMAIN_HELP
sitefordomain.php [options] <email address|domain>
Prints site information for the domain given

END_OF_SITEFORDOMAIN_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once INSTALLDIR.'/plugins/EmailRegistration/extlib/effectiveTLDs.inc.php';
require_once INSTALLDIR.'/plugins/EmailRegistration/extlib/regDomain.inc.php';

function nicknameAvailable($nickname)
{
    $sn = Status_network::staticGet('nickname', $nickname);
    return !empty($sn);
}

function nicknameForDomain($domain)
{
    global $tldTree;

    $registered = getRegisteredDomain($domain, $tldTree);

    $parts = explode('.', $registered);

    $base = $parts[0];

    if (nicknameAvailable($base)) {
        return $base;
    }

    $domainish = str_replace('.', '-', $registered);

    if (nicknameAvailable($domainish)) {
        return $domainish;
    }

    $i = 1;

    // We don't need to keep doing this forever

    while ($i < 1024) {
        $candidate = $domainish.'-'.$i;
        if (nicknameAvailable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$raw = $args[0];


$nickname = nicknameForDomain($domain);

if (empty($nickname)) {
    throw ClientException("No candidate found.");
} else {
    print $nickname;
    print "\n";
}
