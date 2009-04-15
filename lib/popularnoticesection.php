<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of notices
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
 * Base class for sections showing lists of notices
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

class PopularNoticeSection extends NoticeSection
{
    function getNotices()
    {
        if (common_config('db', 'type') == 'pgsql') {
            $weightexpr='sum(exp(-extract(epoch from (now() - fave.modified)) / %s))';
            if (!empty($this->out->tag)) {
                $tag = pg_escape_string($this->tag);
            }
        } else {
            $weightexpr='sum(exp(-(now() - fave.modified) / %s))';
            if (!empty($this->out->tag)) {
                 $tag = mysql_escape_string($this->out->tag);
            }
        }
        $qry = "SELECT notice.*, $weightexpr as weight ";
        if(isset($tag)) {
            $qry .= 'FROM notice_tag, notice JOIN fave ON notice.id = fave.notice_id ' .
                    "WHERE notice.id = notice_tag.notice_id and '$tag' = notice_tag.tag";
        } else {
            $qry .= 'FROM notice JOIN fave ON notice.id = fave.notice_id';
        }
        $qry .= ' GROUP BY notice.id,notice.profile_id,notice.content,notice.uri,' .
                'notice.rendered,notice.url,notice.created,notice.modified,' .
                'notice.reply_to,notice.is_local,notice.source ' .
                'ORDER BY weight DESC';

        $offset = 0;
        $limit  = NOTICES_PER_SECTION + 1;

        if (common_config('db', 'type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $notice = Memcached_DataObject::cachedQuery('Notice',
                                                    sprintf($qry, common_config('popular', 'dropoff')),
                                                    1200);
        return $notice;
    }

    function title()
    {
        return _('Popular notices');
    }

    function divId()
    {
        return 'popular_notices';
    }

    function moreUrl()
    {
        return common_local_url('favorited');
    }
}
