<?php

/**
 * RSS feed for user favorites action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/rssaction.php';

/**
 * RSS feed for user favorites action class.
 *
 * Formatting of RSS handled by Rss10Action
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class FavoritesrssAction extends Rss10Action
{
    
    /** The user whose favorites to display */
    
    var $user = null;
        
    /**
     * Find the user to display by supplied nickname
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return boolean success
     */

    function prepare($args)
    {
        parent::prepare($args);
        
        $nickname   = $this->trimmed('nickname');
        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'));
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Get notices
     *
     * @param integer $limit max number of notices to return
     *
     * @return array notices
     */
    function getNotices($limit=0)
    {
        $user    = $this->user;
        $notice  = $user->favoriteNotices(0, $limit);
        $notices = array();
        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }
        return $notices;
    }

     /**
     * Get channel.
     *
     * @return array associative array on channel information
     */
    function getChannel()
    {
        $user = $this->user;
        $c    = array('url' => common_local_url('favoritesrss',
                                        array('nickname' =>
                                        $user->nickname)),
                   'title' => sprintf(_("%s's favorite notices"), $user->nickname),
                   'link' => common_local_url('showfavorites',
                                        array('nickname' =>
                                        $user->nickname)),
                   'description' => sprintf(_('Feed of favorite notices of %s'), 
                                        $user->nickname));
        return $c;
    }

    /**
     * Get image.
     *
     * @return void
    */
    function getImage()
    {
        return null;
    }

}
