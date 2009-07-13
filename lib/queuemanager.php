<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Abstract class for queue managers
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
 * @category  QueueManager
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

class QueueManager
{
    static $qm = null;

    static function get()
    {
        if (empty(self::$qm)) {

            if (Event::handle('StartNewQueueManager', array(&self::$qm))) {

                $enabled = common_config('queue', 'enabled');
                $type = common_config('queue', 'subsystem');

                if (!$enabled) {
                    // does everything immediately
                    self::$qm = new UnQueueManager();
                } else {
                    switch ($type) {
                     case 'db':
                        self::$qm = new DBQueueManager();
                        break;
                     case 'stomp':
                        self::$qm = new StompQueueManager();
                        break;
                     default:
                        throw new ServerException("No queue manager class for type '$type'");
                    }
                }
            }
        }

        return self::$qm;
    }

    function enqueue($object, $queue)
    {
        throw ServerException("Unimplemented function 'enqueue' called");
    }

    function service($queue, $handler)
    {
        throw ServerException("Unimplemented function 'service' called");
    }
}
