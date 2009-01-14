<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 */

if (!defined('LACONICA')) { exit(1); }

class PersonalAction extends Action
{

    function is_readonly()
    {
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        common_set_returnto($this->self_url());
    }

    function views_menu()
    {

        $user = null;
        $action = $this->trimmed('action');
        $nickname = $this->trimmed('nickname');

        if ($nickname) {
            $user = User::staticGet('nickname', $nickname);
            $user_profile = $user->getProfile();
        } else {
            $user_profile = false;
        }

        common_element_start('ul', array('id' => 'nav_views'));

        common_menu_item(common_local_url('all', array('nickname' =>
                                                       $nickname)),
                         _('Personal'),
                         sprintf(_('%s and friends'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                         $action == 'all');
        common_menu_item(common_local_url('replies', array('nickname' =>
                                                              $nickname)),
                         _('Replies'),
                         sprintf(_('Replies to %s'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                         $action == 'replies');
        common_menu_item(common_local_url('showstream', array('nickname' =>
                                                              $nickname)),
                         _('Profile'),
                         ($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname,
                         $action == 'showstream');
        common_menu_item(common_local_url('showfavorites', array('nickname' =>
                                                              $nickname)),
                         _('Favorites'),
                         sprintf(_('%s\'s favorite notices'), ($user_profile) ? $user_profile->getBestName() : _('User')),
                         $action == 'showfavorites');

        $cur = common_current_user();

        if ($cur && $cur->id == $user->id) {

            common_menu_item(common_local_url('inbox', array('nickname' =>
                                                                     $nickname)),
                             _('Inbox'),
                             _('Your incoming messages'),
                             $action == 'inbox');
            common_menu_item(common_local_url('outbox', array('nickname' =>
                                                                     $nickname)),
                             _('Outbox'),
                             _('Your sent messages'),
                             $action == 'outbox');
        }

        common_element_end('ul');
    }

    function source_link($source)
    {
        $source_name = _($source);
        switch ($source) {
         case 'web':
         case 'xmpp':
         case 'mail':
         case 'omb':
         case 'api':
            common_element('span', 'noticesource', $source_name);
            break;
         default:
            $ns = Notice_source::staticGet($source);
            if ($ns) {
                common_element('a', array('href' => $ns->url),
                               $ns->name);
            } else {
                common_element('span', 'noticesource', $source_name);
            }
            break;
        }
        return;
    }
}
