<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * When a new user registers, all existing users follow them automatically.
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
 * @category  Community
 * @package   StatusNet
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
 * Plugin to make all users follow each other at registration
 *
 * Users can unfollow afterwards if they want.
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class FollowEveryonePlugin extends Plugin
{
    /**
     * Called when a new user is registered.
     *
     * We find all users, and try to subscribe them to the new user, and
     * the new user to them. Exceptions (like silenced users or whatever)
     * are caught, logged, and ignored.
     *
     * @param Profile &$newProfile The new user's profile
     * @param User    &$newUser    The new user
     *
     * @return boolean hook value
     *
     */

    function onEndUserRegister(&$newProfile, &$newUser)
    {
        $otherUser = new User();
        $otherUser->whereAdd('id != ' . $newUser->id);

        if ($otherUser->find()) {
            while ($otherUser->fetch()) {
                $otherProfile = $otherUser->getProfile();
                try {
                    Subscription::start($otherProfile, $newProfile);
                    Subscription::start($newProfile, $otherProfile);
                } catch (Exception $e) {
                    common_log(LOG_WARNING, $e->getMessage());
                    continue;
                }
            }
        }

        return true;
    }

    /**
     * Provide version information about this plugin.
     *
     * @param Array &$versions Array of version data
     *
     * @return boolean hook value
     *
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'FollowEveryone',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:FollowEveryone',
                            'rawdescription' =>
                            _m('New users follow everyone at registration and are followed in return.'));
        return true;
    }
}
