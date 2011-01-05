<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Do a different menu layout
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
 * @category  Sample
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Somewhat different menu navigation
 *
 * We have a new menu layout coming in StatusNet 1.0. This plugin gets
 * some of the new navigation in, although third-level menus aren't enabled.
 *
 * @category  NewMenu
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewMenuPlugin extends Plugin
{
    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'HelloAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'User_greeting_count':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Modify the default menu
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */

    function onStartPrimaryNav($action)
    {
        $user = common_current_user();

        if (!empty($user)) {
            $action->menuItem(common_local_url('all', 
                                               array('nickname' => $user->nickname)),
                              _m('Home'),
                              _m('Friends timeline'),
                              false,
                              'nav_home');
            $action->menuItem(common_local_url('showstream', 
                                               array('nickname' => $user->nickname)),
                              _m('Profile'),
                              _m('Your profile'),
                              false,
                              'nav_profile');
            $action->menuItem(common_local_url('public'),
                              _m('Everyone'),
                              _m('Everyone on this site'),
                              false,
                              'nav_public');
            $action->menuItem(common_local_url('profilesettings'),
                              _m('Settings'),
                              _m('Change your personal settings'),
                              false,
                              'nav_account');
            if ($user->hasRight(Right::CONFIGURESITE)) {
                $action->menuItem(common_local_url('siteadminpanel'),
                                  _m('Admin'), 
                                  _m('Site configuration'),
                                  false,
                                  'nav_admin');
            }
            $action->menuItem(common_local_url('logout'),
                              _m('Logout'), 
                              _m('Logout from the site'),
                              false,
                              'nav_logout');
        } else {
            $action->menuItem(common_local_url('public'),
                              _m('Everyone'),
                              _m('Everyone on this site'),
                              false,
                              'nav_public');
            $action->menuItem(common_local_url('login'),
                              _m('Login'), 
                              _m('Login to the site'),
                              false,
                              'nav_login');
        }

        $action->menuItem(common_local_url('doc', 
                                           array('title' => 'help')),
                          _m('Help'),
                          _m('Help using this site'),
                          false,
                          'nav_help');

        if (!empty($user) || !common_config('site', 'private')) {
            $action->menuItem(common_local_url('noticesearch'),
                              _m('Search'),
                              _m('Search the site'),
                              false,
                              'nav_search');
        }

        Event::handle('EndPrimaryNav', array($action));

        return false;
    }

    /**
     * Return version information for this plugin
     *
     * @param array &$versions Version info; add to this array
     *
     * @return boolean hook value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'NewMenu',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:NewMenu',
                            'description' =>
                            _m('A preview of the new menu '.
                               'layout in StatusNet 1.0.'));
        return true;
    }
}
