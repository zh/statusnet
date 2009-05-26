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

class SubscriptionsPeopleTagCloudSection extends SubPeopleTagCloudSection
{
    function title()
    {
        return _('People Tagcloud as tagged');
    }

    function tagUrl($tag) {
        $nickname = $this->out->profile->nickname;
        return common_local_url('subscriptions', array('nickname' => $nickname, 'tag' => $tag));
    }

    function query() {
//        return 'select tag, count(tag) as weight from subscription left join profile_tag on subscriber=tagger and subscribed=tagged where subscriber=%d and subscriber != subscribed group by tag order by weight desc';
        return 'select tag, count(tag) as weight from subscription left join profile_tag on subscriber=tagger and subscribed=tagged where subscriber=%d and subscriber != subscribed and tag is not null group by tag order by weight desc';
    }
}

