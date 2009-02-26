<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Section for featured users
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
 * @category  Widget
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Section for featured users
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class FeaturedUsersSection extends ProfileSection
{
    function getProfiles()
    {
        $featured_nicks = common_config('nickname', 'featured');

        if (!$featured_nicks) {
            return null;
        }

        $quoted = array();

        foreach ($featured_nicks as $nick) {
            $quoted[] = "'$nick'";
        }

        $qry = 'SELECT profile.* ' .
          'FROM profile JOIN user on profile.id = user.id ' .
          'WHERE user.nickname in (' . implode(',', $quoted) . ') ' .
          'ORDER BY profile.created DESC ';

        $limit = PROFILES_PER_SECTION + 1;
        $offset = 0;

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $profile = Memcached_DataObject::cachedQuery('Profile',
                                                     $qry,
                                                     6 * 3600);
        return $profile;
    }

    function title()
    {
        return _('Featured users');
    }

    function divId()
    {
        return 'featured_users';
    }

    function moreUrl()
    {
        return common_local_url('featured');
    }
}
