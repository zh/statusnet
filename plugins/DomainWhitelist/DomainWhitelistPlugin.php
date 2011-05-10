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
 * @author    Zach Copley <zach@status.net>
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DomainWhitelistPlugin extends Plugin
{
    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false
     *         means stop.
     */
    function onAutoload($cls) {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);

        $files = array("$base/classes/$cls.php",
            "$base/lib/$lower.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    /**
     * Get the path to the plugin's installation directory. Used
     * to link in js files and whatnot.
     *
     * @return String the absolute path
     */
    protected function getPath() {
        return preg_replace('/^' . preg_quote(INSTALLDIR, '/') . '\//', '', dirname(__FILE__));
    }

    /**
     * Link in a JavaScript script for the whitelist invite form
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    function onEndShowStatusNetScripts($action) {
        $name = $action->arg('action');
        if ($name == 'invite') {
            $action->script($this->getPath() . '/js/whitelistinvite.js');
        }
        return true;
    }

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

    /**
     * Show a fancier invite form when domains are restricted to the
     * whitelist.
     *
     * @param action $action the invite action
     * @return boolean hook value
     */
    function onStartShowInviteForm($action)
    {
        $form = new WhitelistInviteForm($action, $this->getWhitelist());
        $form->show();
        return false;
    }

    /**
     * This is a bit of a hack. We take the values from the custom
     * whitelist invite form and reformat them so they look like
     * their coming from the the normal invite form.
     *
     * @param action &$action the invite action
     * @return boolean hook value
     */
    function onStartSendInvitations(&$action)
    {
       $emails    = array();
       $usernames = $action->arg('username');
       $domains   = $action->arg('domain');

       for($i = 0; $i < count($usernames); $i++) {
           if (!empty($usernames[$i])) {
               $emails[] = $usernames[$i] . '@' . $domains[$i] . "\n";
           }
       }

       $action->args['addresses'] = implode($emails);

       return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'DomainWhitelist',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou, Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:DomainWhitelist',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Restrict domains for email users.'));
        return true;
    }
}
