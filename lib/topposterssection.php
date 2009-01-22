<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of people
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
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class TopPostersSection extends ProfileSection
{
    function getProfiles()
    {
        $qry = 'SELECT profile.*, count(*) as value ' .
          'FROM profile JOIN notice ON profile.id = notice.profile_id ' .
          (common_config('public', 'localonly') ? 'WHERE is_local = 1 ' : '') .
          'GROUP BY profile.id ' .
          'ORDER BY value DESC ';

        $limit = PROFILES_PER_SECTION;
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

    function showProfile($profile)
    {
        $this->out->elementStart('tr');
        $this->out->elementStart('td');
        $this->out->elementStart('span', 'vcard');
        $this->out->elementStart('a', array('title' => ($profile->fullname) ?
                                       $profile->fullname :
                                       $profile->nickname,
                                       'href' => $profile->profileurl,
                                       'rel' => 'contact member',
                                       'class' => 'url'));
        $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);
        $this->out->element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
                                    'width' => AVATAR_MINI_SIZE,
                                    'height' => AVATAR_MINI_SIZE,
                                    'class' => 'avatar photo',
                                    'alt' =>  ($profile->fullname) ?
                                    $profile->fullname :
                                    $profile->nickname));
        $this->out->element('span', 'fn nickname', $profile->nickname);
        $this->out->elementEnd('span');
        $this->out->elementEnd('a');
        $this->out->elementEnd('td');
        if ($profile->value) {
            $this->out->element('td', 'value', $profile->value);
        }

        $this->out->elementEnd('tr');
    }

    function title()
    {
        return _('Top posters');
    }

    function divId()
    {
        return 'top_posters';
    }
}
