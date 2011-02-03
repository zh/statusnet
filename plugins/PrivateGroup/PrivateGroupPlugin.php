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
                                                 'timestamp')));
                             
        $schema->ensureTable('group_message',
                             array(new ColumnDef('id',
                                                 'char',
                                                 36,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('uri',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'UNI'),
                                   new ColumnDef('from_profile',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('to_group',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('content',
                                                 'text'),
                                   new ColumnDef('rendered',
                                                 'text'),
                                   new ColumnDef('url',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'UNI'),
                                   new ColumnDef('created',
                                                 'datetime')));

        $schema->ensureTable('group_message_profile',
                             array(new ColumnDef('to_profile',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('group_message_id',
                                                 'char',
                                                 36,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('created',
                                                 'datetime')));

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
        case 'Group_message':
        case 'Group_message_profile':
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

    /**
     * Create default group privacy settings at group create time
     *
     * @param User_group $group Group that was just created
     *
     * @result boolean hook value
     */

    function onEndGroupSave($group)
    {
        $gps = new Group_privacy_settings();

        $gps->group_id      = $group->id;
        $gps->allow_privacy = Group_privacy_settings::SOMETIMES;
        $gps->allow_sender  = Group_privacy_settings::MEMBER;
        $gps->created       = common_sql_now();
        $gps->modified      = $gps->created;

        // This will throw an exception on error

        $gps->insert();

        return true;
    }

    /**
     * Show group privacy controls on group edit form
     *
     * @param GroupEditForm $form form being shown
     */

    function onEndGroupEditFormData($form)
    {
        $gps = null;

        if (!empty($form->group)) {
            $gps = Group_privacy_settings::staticGet('group_id', $form->group->id);
        }

        $form->out->elementStart('li');
        $form->out->dropdown('allow_privacy',
                             _('Private messages'),
                             array(Group_privacy_settings::SOMETIMES => _('Sometimes'),
                                   Group_privacy_settings::ALWAYS => _('Always'),
                                   Group_privacy_settings::NEVER => _('Never')),
                             _('Whether to allow private messages to this group'),
                             false,
                             (empty($gps)) ? Group_privacy_settings::SOMETIMES : $gps->allow_privacy);
        $form->out->elementEnd('li');
        $form->out->elementStart('li');
        $form->out->dropdown('allow_sender',
                             _('Private sender'),
                             array(Group_privacy_settings::EVERYONE => _('Everyone'),
                                   Group_privacy_settings::MEMBER => _('Member'),
                                   Group_privacy_settings::ADMIN => _('Admin')),
                             _('Who can send private messages to the group'),
                             false,
                             (empty($gps)) ? Group_privacy_settings::MEMBER : $gps->allow_sender);
        $form->out->elementEnd('li');
        return true;
    }

    function onEndGroupSaveForm($action)
    {
        $gps = null;

        if (!empty($action->group)) {
            $gps = Group_privacy_settings::staticGet('group_id', $action->group->id);
        }

        $orig = null;

        if (empty($gps)) {
            $gps = new Group_privacy_settings();
            $gps->group_id = $action->group->id;
        } else {
            $orig = clone($gps);
        }
        
        $gps->allow_privacy = $action->trimmed('allow_privacy');
        $gps->allow_sender  = $action->trimmed('allow_sender');

        if (empty($orig)) {
            $gps->created = common_sql_now();
            $gps->insert();
        } else {
            $gps->update($orig);
        }
        
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
