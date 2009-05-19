<?php

/**
 * Public RSS action class.
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
 * RSS feed for public timeline.
 *
 * Formatting of RSS handled by Rss10Action
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class PublicrssAction extends Rss10Action
{
    /**
     * Initialization.
     * 
     * @return boolean true
     */
    function init()
    {
        return true;
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
        $notices = array();
        $notice  = Notice::publicStream(0, ($limit == 0) ? 48 : $limit);
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
        $c = array(
              'url' => common_local_url('publicrss')
            , 'title' => sprintf(_('%s Public Stream'), common_config('site', 'name'))
            , 'link' => common_local_url('public')
            , 'description' => sprintf(_('All updates for %s'), common_config('site', 'name')));
        return $c;
    }

    /**
     * Get image.
     *
     * @return nothing
    */
    function getImage()
    {
        // nop
    }

    function isReadOnly($args)
    {
        return true;
    }
}

