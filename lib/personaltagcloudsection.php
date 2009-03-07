<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Personal tag cloud section
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
 * Personal tag cloud section
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class PersonalTagCloudSection extends TagCloudSection
{
    var $user = null;

    function __construct($out=null, $user=null)
    {
        parent::__construct($out);
        $this->user = $user;
    }

    function title()
    {
        return sprintf(_('Tags in %s\'s notices'), $this->user->nickname);
    }

    function getTags()
    {
        if (common_config('db', 'type') == 'pgsql') {
            $weightexpr='sum(exp(-extract(epoch from (now() - notice_tag.created)) / %s))';
        } else {
            $weightexpr='sum(exp(-(now() - notice_tag.created) / %s))';
        }
 
       $qry = 'SELECT notice_tag.tag, '.
          $weightexpr . ' as weight ' .
          'FROM notice_tag JOIN notice ' .
          'ON notice_tag.notice_id = notice.id ' .
          'WHERE notice.profile_id = %d ' .
          'GROUP BY notice_tag.tag ' .
          'ORDER BY weight DESC ';

        $limit = TAGS_PER_SECTION;
        $offset = 0;

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $tag = Memcached_DataObject::cachedQuery('Notice_tag',
                                                 sprintf($qry,
                                                         common_config('tag', 'dropoff'),
                                                         $this->user->id),
                                                 3600);
        return $tag;
    }

}
