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

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class FavoritesrssAction extends Rss10Action {

    var $user = null;
    
    function init()
    {
        $nickname = $this->trimmed('nickname');
        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            common_user_error(_('No such user.'));
            return false;
        } else {
            return true;
        }
    }

    function get_notices($limit=0)
    {

        $user = $this->user;

        $notice = $user->favoriteNotices(0, $limit);

        $notices = array();

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    function get_channel()
    {
        $user = $this->user;
        $c = array('url' => common_local_url('favoritesrss',
                                             array('nickname' =>
                                                   $user->nickname)),
                   'title' => sprintf(_("%s favorite notices"), $user->nickname),
                   'link' => common_local_url('showfavorites',
                                             array('nickname' =>
                                                   $user->nickname)),
                   'description' => sprintf(_('Feed of favorite notices of %s'), $user->nickname));
        return $c;
    }

    function get_image()
    {
        return null;
    }
}