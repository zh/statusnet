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

class PersonalAction extends Action {
    
    function is_readonly() {
        return true;
    }
    
    function handle($args) {
        parent::handle($args);
        common_set_returnto($this->self_url());
    }

    function views_menu() {

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

    function show_feeds_list($feeds) {
        common_element_start('div', array('class' => 'feeds'));
        common_element('p', null, 'Feeds:');
        common_element_start('ul', array('class' => 'xoxo'));

        foreach ($feeds as $key => $value) {
            $this->common_feed_item($feeds[$key]);
        }
        common_element_end('ul');
        common_element_end('div');
    }

    function common_feed_item($feed) {
        $nickname = $this->trimmed('nickname');

        switch($feed['item']) {
            case 'notices': default:
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's ".$feed['version']." notice feed";
                $feed['textContent'] = "RSS";
                break;

            case 'allrss':
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = $feed['version']." feed for $nickname and friends";
                $feed['textContent'] = "RSS";
                break;

            case 'repliesrss':
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = $feed['version']." feed for replies to $nickname";
                $feed['textContent'] = "RSS";
                break;

            case 'publicrss':
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "Public timeline ".$feed['version']." feed";
                $feed['textContent'] = "RSS";
                break;

            case 'publicatom':
                $feed_classname = "atom";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "Public timeline ".$feed['version']." feed";
                $feed['textContent'] = "Atom";
                break;

            case 'tagrss':
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = $feed['version']." feed for this tag";
                $feed['textContent'] = "RSS";
                break;

            case 'favoritedrss':
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "Favorited ".$feed['version']." feed";
                $feed['textContent'] = "RSS";
                break;

            case 'foaf':
                $feed_classname = "foaf";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's FOAF file";
                $feed['textContent'] = "FOAF";
                break;

            case 'favoritesrss':
                $feed_classname = "favorites";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "Feed for favorites of $nickname";
                $feed['textContent'] = "RSS";
                break;

            case 'usertimeline':
                $feed_classname = "atom";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's ".$feed['version']." notice feed";
                $feed['textContent'] = "Atom";
                break;
        }
        common_element_start('li');
        common_element('a', array('href' => $feed['href'],
                                  'class' => $feed_classname,
                                  'type' => $feed_mimetype,
                                  'title' => $feed_title),
                            $feed['textContent']);
        common_element_end('li');
    }

    
    function source_link($source) {
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
