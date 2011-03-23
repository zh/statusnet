<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A stream of notices
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
 * Class for notice streams
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class NoticeStream
{
    public $generator = null;
    public $args      = null;
    public $cachekey  = null;

    function __construct($generator, $args, $cachekey)
    {
        $this->generator = $generator;
        $this->args      = $args;
        $this->cachekey  = $cachekey;
    }

    function getNotices($offset=0, $limit=20, $sinceId=0, $maxId=0)
    {
        $ids = $this->getNoticeIds($offset, $limit, $sinceId, $maxId);

        $notices = $this->getStreamByIds($ids);

        return $notices;
    }

    function getNoticeIds($offset=0, $limit=20, $sinceId=0, $maxId=0)
    {
        $cache = Cache::instance();

        // We cache NOTICE_CACHE_WINDOW elements at the tip of the stream.
        // If the cache won't be hit, just generate directly.

        if (empty($cache) ||
            $sinceId != 0 || $maxId != 0 ||
            is_null($limit) ||
            ($offset + $limit) > NOTICE_CACHE_WINDOW) {
            return $this->generate($offset, $limit, $sinceId, $maxId);
        }

        // Check the cache to see if we have the stream.

        $idkey = Cache::key($this->cachekey);

        $idstr = $cache->get($idkey);

        if ($idstr !== false) {
            // Cache hit! Woohoo!
            $window = explode(',', $idstr);
            $ids = array_slice($window, $offset, $limit);
            return $ids;
        }

        // Check the cache to see if we have a "last-known-good" version.
        // The actual cache gets blown away when new notices are added, but
        // the "last" value holds a lot of info. We might need to only generate
        // a few at the "tip", which can bound our queries and save lots
        // of time.

        $laststr = $cache->get($idkey.';last');

        if ($laststr !== false) {
            $window = explode(',', $laststr);
            $last_id = $window[0];
            $new_ids = $this->generate(0, NOTICE_CACHE_WINDOW, $last_id, 0);

            $new_window = array_merge($new_ids, $window);

            $new_windowstr = implode(',', $new_window);

            $result = $cache->set($idkey, $new_windowstr);
            $result = $cache->set($idkey . ';last', $new_windowstr);

            $ids = array_slice($new_window, $offset, $limit);

            return $ids;
        }

        // No cache hits :( Generate directly and stick the results
        // into the cache. Note we generate the full cache window.

        $window = $this->generate(0, NOTICE_CACHE_WINDOW, 0, 0);

        $windowstr = implode(',', $window);

        $result = $cache->set($idkey, $windowstr);
        $result = $cache->set($idkey . ';last', $windowstr);

        // Return just the slice that was requested

        $ids = array_slice($window, $offset, $limit);

        return $ids;
    }

    function getStreamByIds($ids)
    {
        $cache = Cache::instance();

        if (!empty($cache)) {
            $notices = array();
            foreach ($ids as $id) {
                $n = Notice::staticGet('id', $id);
                if (!empty($n)) {
                    $notices[] = $n;
                }
            }
            return new ArrayWrapper($notices);
        } else {
            $notice = new Notice();
            if (empty($ids)) {
                //if no IDs requested, just return the notice object
                return $notice;
            }
            $notice->whereAdd('id in (' . implode(', ', $ids) . ')');

            $notice->find();

            $temp = array();

            while ($notice->fetch()) {
                $temp[$notice->id] = clone($notice);
            }

            $wrapped = array();

            foreach ($ids as $id) {
                if (array_key_exists($id, $temp)) {
                    $wrapped[] = $temp[$id];
                }
            }

            return new ArrayWrapper($wrapped);
        }
    }

    function generate($offset, $limit, $sinceId, $maxId)
    {
        $args = array_merge($this->args, array($offset,
                                               $limit,
                                               $sinceId,
                                               $maxId));

        return call_user_func_array($this->generator, $args);
    }
}
