<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Stream of notices for a people tag
 *
 * PHP version 5
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
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Stream of notices for a people tag
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class PeopletagNoticeStream extends ScopingNoticeStream
{
    function __construct($plist)
    {
        parent::__construct(new CachingNoticeStream(new RawPeopletagNoticeStream($plist),
                                                    'profile_tag:notice_ids:' . $plist->id));
    }
}

/**
 * Stream of notices for a people tag
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RawPeopletagNoticeStream extends NoticeStream
{
    protected $profile_tag;

    function __construct($profile_tag)
    {
        $this->profile_tag = $profile_tag;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $inbox = new Profile_tag_inbox();

        $inbox->profile_tag_id = $this->profile_tag->id;

        $inbox->selectAdd();
        $inbox->selectAdd('notice_id');

        Notice::addWhereSinceId($inbox, $since_id, 'notice_id');
        Notice::addWhereMaxId($inbox, $max_id, 'notice_id');

        $inbox->orderBy('created DESC, notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
    }
}
