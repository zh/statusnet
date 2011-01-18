<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Private groups for StatusNet 0.9.x
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
 * @category  Privacy
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
 * Private groups
 *
 * This plugin allows users to send private messages to a group.
 *
 * @category  Privacy
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class PrivateGroupPlugin extends Plugin
{
    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles

        $schema->ensureTable('group_privacy_settings',
                             array(new ColumnDef('group_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('allow_privacy',
                                                 'integer'),
                                   new ColumnDef('allow_sender',
                                                 'integer'),
                                   new ColumnDef('created',
                                                 'datetime'),
                                   new ColumnDef('modified',
                                                 'timestamp'));

        $schema->ensureTable('group_private_inbox',
                             array(new ColumnDef('group_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('allow_privacy',
                                                 'integer'),
                                   new ColumnDef('allow_sender',
                                                 'integer'),
                                   new ColumnDef('created',
                                                 'datetime'),
                                   new ColumnDef('modified',
                                                 'timestamp'));

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'GroupinboxAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'Group_privacy_settings':
        case 'Group_private_inbox':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value
     */

    function onRouterInitialized($m)
    {
        $m->connect('group/:nickname/inbox',
                    array('action' => 'groupinbox'),
                    array('nickname' => Nickname::DISPLAY_FMT));

        return true;
    }

    /**
     * Add group inbox to the menu
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */

    function onEndGroupGroupNav($groupnav)
    {
        $action = $groupnav->action;
        $group  = $groupnav->group;

        $action->menuItem(common_local_url('groupinbox',
                                           array('nickname' => $group->nickname)),
                          _m('Inbox'),
                          _m('Private messages for this group'),
                          $action->trimmed('action') == 'groupinbox',
                          'nav_group_inbox');
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'PrivateGroup',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:PrivateGroup',
                            'rawdescription' =>
                            _m('Allow posting DMs to a group.'));
        return true;
    }
}
