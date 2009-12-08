<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

define('TWITTERBRIDGEPLUGIN_VERSION', '0.9');

/**
 * Plugin for sending and importing Twitter statuses
 *
 * This class allows users to link their Twitter accounts
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
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
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook return
     */

    function onRouterInitialized($m)
    {
        $m->connect('twitter/authorization',
                    array('action' => 'twitterauthorization'));
        $m->connect('settings/twitter', array('action' => 'twittersettings'));
        
        $m->connect('main/twitterlogin', array('action' => 'twitterlogin'));

        return true;
    }
    
    
    
    /*
     * Add a login tab for Twitter Connect
     *
     * @param Action &action the current action
     *
     * @return void
     */
    function onEndLoginGroupNav(&$action)
    {

        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('twitterlogin'),
                                           _('Twitter'),
                                           _('Login or register using Twitter'),
                                             'twitterlogin' === $action_name);

        return true;
    }
    

    /**
     * Add the Twitter Settings page to the Connect Settings menu
     *
     * @param Action &$action The calling page
     *
     * @return boolean hook return
     */
    function onEndConnectSettingsNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('twittersettings'),
                          _m('Twitter'),
                          _m('Twitter integration options'),
                          $action_name === 'twittersettings');

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the Twitter bridge
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        switch ($cls) {
        case 'TwittersettingsAction':
        case 'TwitterauthorizationAction':
        case 'TwitterloginAction':
            include_once INSTALLDIR . '/plugins/TwitterBridge/' .
              strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'TwitterOAuthClient':
        case 'TwitterQueueHandler':
            include_once INSTALLDIR . '/plugins/TwitterBridge/' .
              strtolower($cls) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Add a Twitter queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        // Avoid a possible loop

        if ($notice->source != 'twitter') {
            array_push($transports, 'twitter');
        }

        return true;
    }

    /**
     * Add Twitter bridge daemons to the list of daemons to start
     *
     * @param array $daemons the list fo daemons to run
     *
     * @return boolean hook return
     */
    function onGetValidDaemons($daemons)
    {
        array_push($daemons, INSTALLDIR .
                   '/plugins/TwitterBridge/daemons/synctwitterfriends.php');

        if (common_config('twitterimport', 'enabled')) {
            array_push($daemons, INSTALLDIR
                . '/plugins/TwitterBridge/daemons/twitterstatusfetcher.php');
        }

        return true;
    }

    /**
     * Register Twitter notice queue handler
     *
     * @param QueueManager $manager
     *
     * @return boolean hook return
     */
    function onEndInitializeQueueManager($manager)
    {
        $manager->connect('twitter', 'TwitterQueueHandler');
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'TwitterBridge',
                            'version' => TWITTERBRIDGEPLUGIN_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:TwitterBridge',
                            'rawdescription' =>
                            _m('The Twitter "bridge" plugin allows you to integrate ' .
                               'your StatusNet instance with ' .
                               '<a href="http://twitter.com/">Twitter</a>.'));
        return true;
    }

}
