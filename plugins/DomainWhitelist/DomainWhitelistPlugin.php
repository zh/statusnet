<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Restrict the email addresses in a domain to a select whitelist
 *
 * PHP version 5
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
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Restrict the email addresses to a domain whitelist
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DomainWhitelistPlugin extends Plugin
{
    function onRequireValidatedEmailPlugin_Override($user, &$knownGood)
    {
        $knownGood = (!empty($user->email) && $this->matchesWhitelist($user->email));
        return true;
    }

    function onEndValidateUserEmail($user, $email, &$valid)
    {
        if ($valid) { // it's otherwise valid
            if (!$this->matchesWhitelist($email)) {
                $whitelist = $this->getWhitelist();
                if (count($whitelist) == 1) {
                    // TRANS: Client exception thrown when a given e-mailaddress is not in the domain whitelist.
                    // TRANS: %s is a whitelisted e-mail domain.
                    $message = sprintf(_m('Email address must be in this domain: %s.'),
                                       $whitelist[0]);
                } else {
                    // TRANS: Client exception thrown when a given e-mailaddress is not in the domain whitelist.
                    // TRANS: %s are whitelisted e-mail domains separated by comma's (localisable).
                    $message = sprintf(_('Email address must be in one of these domains: %s.'),
                                       // TRANS: Separator for whitelisted domains.
                                       implode(_m('SEPARATOR',', '), $whitelist));
                }
                throw new ClientException($message);
            }
        }
        return true;
    }

    function onStartAddEmailAddress($user, $email)
    {
        if (!$this->matchesWhitelist($email)) {
            // TRANS: Exception thrown when an e-mail address does not match the site's domain whitelist.
            throw new Exception(_('That email address is not allowed on this site.'));
        }

        return true;
    }

    function onEndValidateEmailInvite($user, $email, &$valid)
    {
        if ($valid) {
            $valid = $this->matchesWhitelist($email);
        }

        return true;
    }

    function matchesWhitelist($email)
    {
        $whitelist = $this->getWhitelist();

        if (empty($whitelist) || empty($whitelist[0])) {
            return true;
        }

        $parts = explode('@', $email);

        $userDomain = strtolower(trim($parts[1]));

        return in_array($userDomain, $whitelist);
    }

    function getWhitelist()
    {
        $whitelist = common_config('email', 'whitelist');

        if (is_array($whitelist)) {
            return $whitelist;
        } else {
            return explode('|', $whitelist);
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'DomainWhitelist',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:DomainWhitelist',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Restrict domains for email users.'));
        return true;
    }
}
