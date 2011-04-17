<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Email-based registration, as on the StatusNet OnDemand service
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
 * @category  Email registration
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
 * Email based registration plugin
 *
 * @category  Email registration
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class EmailRegistrationPlugin extends Plugin
{
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'EmailregisterAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Hijack main/register
     */

    function onStartConnectPath(&$path, &$defaults, &$rules, &$result)
    {
        static $toblock = array('main/register', 'main/register/:code');

        if (in_array($path, $toblock)) {
            common_debug("Request came in for $path");
            if ($defaults['action'] != 'emailregister') {
                common_debug("Action is {$default['action']}, so: rejected.");
                $result = false;
                return false;
            }
        }

        return true;
    }

    function onStartInitializeRouter($m)
    {
        $m->connect('main/register/:code', array('action' => 'emailregister'));
        $m->connect('main/register', array('action' => 'emailregister'));

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'EmailRegistration',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:EmailRegistration',
                            'rawdescription' =>
                            _m('Use email only for registration'));
        return true;
    }
}
