<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add a StatusNet Facebook application
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Facebook plugin to add a StatusNet Facebook application
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class FacebookPlugin extends Plugin
{

    /**
     * Add Facebook app actions to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper &$m path-to-action mapper
     *
     * @return boolean hook return
     */

    function onRouterInitialized(&$m)
    {
        $m->connect('facebook', array('action' => 'facebookhome'));
        $m->connect('facebook/index.php', array('action' => 'facebookhome'));
        $m->connect('facebook/settings.php', array('action' => 'facebooksettings'));
        $m->connect('facebook/invite.php', array('action' => 'facebookinvite'));
        $m->connect('facebook/remove', array('action' => 'facebookremove'));

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the Facebook app
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        switch ($cls) {
        case 'FacebookAction':
        case 'FacebookhomeAction':
        case 'FacebookinviteAction':
        case 'FacebookremoveAction':
        case 'FacebooksettingsAction':
            include_once INSTALLDIR . '/plugins/Facebook/' .
              strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Add a Facebook queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        array_push($transports, 'facebook');
        return true;
    }

    /**
     * broadcast the message when not using queuehandler
     *
     * @param Notice &$notice the notice
     * @param array  $queue   destination queue
     *
     * @return boolean hook return
     */
    function onUnqueueHandleNotice(&$notice, $queue)
    {
        if (($queue == 'facebook') && ($this->_isLocal($notice))) {
            facebookBroadcastNotice($notice);
            return false;
        }
        return true;
    }

    /**
     * Determine whether the notice was locally created
     *
     * @param Notice $notice
     *
     * @return boolean locality
     */
    function _isLocal($notice)
    {
        return ($notice->is_local == Notice::LOCAL_PUBLIC ||
                $notice->is_local == Notice::LOCAL_NONPUBLIC);
    }

    /**
     * Add Facebook queuehandler to the list of daemons to start
     *
     * @param array $daemons the list fo daemons to run
     *
     * @return boolean hook return
     *
     */
    function onGetValidDaemons($daemons)
    {
        array_push($daemons, INSTALLDIR .
                   '/plugins/Facebook/facebookqueuehandler.php');
        return true;
    }

}