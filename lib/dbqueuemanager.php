<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Simple-minded queue manager for storing items in the database
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

class DBQueueManager extends QueueManager
{
    var $qis = array();

    function enqueue($object, $queue)
    {
        $notice = $object;

        $qi = new Queue_item();

        $qi->notice_id = $notice->id;
        $qi->transport = $queue;
        $qi->created   = $notice->created;
        $result        = $qi->insert();

        if (!$result) {
            common_log_db_error($qi, 'INSERT', __FILE__);
            throw new ServerException('DB error inserting queue item');
        }

        return true;
    }

    function service($queue, $handler)
    {
        while (true) {
            $this->_log(LOG_DEBUG, 'Checking for notices...');
            $notice = $this->_nextItem($queue, null);
            if (empty($notice)) {
                $this->_log(LOG_DEBUG, 'No notices waiting; idling.');
                // Nothing in the queue. Do you
                // have other tasks, like servicing your
                // XMPP connection, to do?
                $handler->idle(QUEUE_HANDLER_MISS_IDLE);
            } else {
                $this->_log(LOG_INFO, 'Got notice '. $notice->id);
                // Yay! Got one!
                if ($handler->handle_notice($notice)) {
                    $this->_log(LOG_INFO, 'Successfully handled notice '. $notice->id);
                    $this->_done($notice, $queue);
                } else {
                    $this->_log(LOG_INFO, 'Failed to handle notice '. $notice->id);
                    $this->_fail($notice, $queue);
                }
                // Chance to e.g. service your XMPP connection
                $this->_log(LOG_DEBUG, 'Idling after success.');
                $handler->idle(QUEUE_HANDLER_HIT_IDLE);
            }
            // XXX: when do we give up?
        }
    }

    function _nextItem($queue, $timeout=null)
    {
        $start = time();
        $result = null;

        do {
            $qi = Queue_item::top($queue);
            if (!empty($qi)) {
                $notice = Notice::staticGet('id', $qi->notice_id);
                if (!empty($notice)) {
                    $result = $notice;
                } else {
                    $this->_log(LOG_INFO, 'dequeued non-existent notice ' . $notice->id);
                    $qi->delete();
                    $qi->free();
                    $qi = null;
                }
            }
        } while (empty($result) && (is_null($timeout) || (time() - $start) < $timeout));

        return $result;
    }

    function _done($object, $queue)
    {
        // XXX: right now, we only handle notices

        $notice = $object;

        $qi = Queue_item::pkeyGet(array('notice_id' => $notice->id,
                                        'transport' => $queue));

        if (empty($qi)) {
            $this->_log(LOG_INFO, 'Cannot find queue item for notice '.$notice->id.', queue '.$queue);
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, 'Reluctantly releasing unclaimed queue item '.
                           'for '.$notice->id.', queue '.$queue);
            }
            $qi->delete();
            $qi->free();
            $qi = null;
        }

        $this->_log(LOG_INFO, 'done with notice ID = ' . $notice->id);

        $notice->free();
        $notice = null;
    }

    function _fail($object, $queue)
    {
        // XXX: right now, we only handle notices

        $notice = $object;

        $qi = Queue_item::pkeyGet(array('notice_id' => $notice->id,
                                        'transport' => $queue));

        if (empty($qi)) {
            $this->_log(LOG_INFO, 'Cannot find queue item for notice '.$notice->id.', queue '.$queue);
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, 'Ignoring failure for unclaimed queue item '.
                           'for '.$notice->id.', queue '.$queue);
            } else {
                $orig = clone($qi);
                $qi->claimed = null;
                $qi->update($orig);
                $qi = null;
            }
        }

        $this->_log(LOG_INFO, 'done with notice ID = ' . $notice->id);

        $notice->free();
        $notice = null;
    }

    function _log($level, $msg)
    {
        common_log($level, 'DBQueueManager: '.$msg);
    }
}
