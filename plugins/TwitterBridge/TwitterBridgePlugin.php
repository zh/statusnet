<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   Laconica
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Plugin for sending and importing Twitter statuses
 *
 * This class allows users to link their Twitter accounts
 *
 * @category Plugin
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 * @link     http://twitter.com/
 */

class TwitterBridgePlugin extends Plugin
{
    /**
     * Initializer for the plugin.
     */

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Add Twitter-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @return boolean hook return
     */

    function onRouterInitialized(&$m)
    {
        $m->connect('twitter/authorization', array('action' => 'twitterauthorization'));
        $m->connect('settings/twitter', array('action' => 'twittersettings'));

        return true;
    }

    function onEndConnectSettingsNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('twittersettings'),
                          _('Twitter'),
                          _('Twitter integration options'),
                          $action_name === 'twittersettings');

        return true;
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'TwittersettingsAction':
         case 'TwitterauthorizationAction':
            require_once(INSTALLDIR.'/plugins/TwitterBridge/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
            return false;
         case 'TwitterOAuthClient':
            require_once(INSTALLDIR.'/plugins/TwitterBridge/twitteroAuthclient.php');
            return false;
         default:
            return true;
        }
    }


}