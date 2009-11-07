<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Allows users to flag content and accounts as offensive/spam/whatever
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Allows users to flag content and accounts as offensive/spam/whatever
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class UserFlagPlugin extends Plugin
{
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles

        $schema->ensureTable('user_flag_profile',
                             array(new ColumnDef('profile_id', 'integer', null,
                                                 null, 'PRI'),
                                   new ColumnDef('user_id', 'integer', null,
                                                 null, 'PRI'),
                                   new ColumnDef('created', 'datetime', null,
                                                 null, 'MUL'),
                                   new ColumnDef('cleared', 'datetime', null,
                                                 null, 'MUL')));

        return true;
    }

    function onInitializePlugin()
    {
        // XXX: do something here?
        return true;
    }

    function onRouterInitialized(&$m) {
        $m->connect('main/flag/profile', array('action' => 'flagprofile'));
        $m->connect('admin/profile/flag', array('action' => 'adminprofileflag'));
        return true;
    }

   function onAutoload($cls)
    {
        switch ($cls)
        {
        case 'FlagprofileAction':
        case 'AdminprofileflagAction':
            require_once(INSTALLDIR.'/plugins/UserFlag/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
            return false;
        case 'FlagProfileForm':
            require_once(INSTALLDIR.'/plugins/UserFlag/' . strtolower($cls . '.php'));
            return false;
        case 'User_flag_profile':
            require_once(INSTALLDIR.'/plugins/UserFlag/'.$cls.'.php');
            return false;
        default:
            return true;
        }
    }

    function onEndProfilePageActionsElements(&$action, $profile)
    {
        $user = common_current_user();

        if (!empty($user)) {

            $action->elementStart('li', 'entity_flag');

            if (User_flag_profile::exists($profile->id, $user->id,
                                          Profile_flag::DEFAULTFLAG)) {
                $action->element('span',
                                 _('Flagged for review'));
            } else {
                $form = new FlagProfileForm($action, $profile,
                                        array('action' => 'showstream',
                                              'nickname' => $profile->nickname));
                $form->show();
            }

            $action->elementEnd('li');
        }

        return true;
    }

    function onEndProfileListItemActionElements($item)
    {
        $user = common_current_user();

        if (!empty($user)) {

            $form = new FlagProfileForm($item->action, $item->profile);

            $form->show();
        }

        return true;
    }
}
