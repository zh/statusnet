<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class DBQueueManager extends QueueManager
{
    /**
     * Saves an object into the queue item table.
     * @return boolean true on success
     * @throws ServerException on failure
     */
    public function enqueue($object, $queue)
    {
        $qi = new Queue_item();

        $qi->frame     = serialize($object);
        $qi->transport = $queue;
        $qi->created   = common_sql_now();
        $result        = $qi->insert();

        if (!$result) {
            common_log_db_error($qi, 'INSERT', __FILE__);
            throw new ServerException('DB error inserting queue item');
        }

        $this->stats('enqueued', $queue);

        return true;
    }

    /**
     * Poll every minute for new events during idle periods.
     * We'll look in more often when there's data available.
     *
     * @return int seconds
     */
    public function pollInterval()
    {
        return 60;
    }

    /**
     * Run a polling cycle during idle processing in the input loop.
     * @return boolean true if we had a hit
     */
    public function poll()
    {
        $this->_log(LOG_DEBUG, 'Checking for queued objects...');
        $qi = $this->_nextItem();
        if ($qi === false) {
            $this->_log(LOG_DEBUG, 'No queue items waiting; idling.');
            return false;
        }
        if ($qi === true) {
            // We dequeued an entry for a deleted or invalid object.
            // Consider it a hit for poll rate purposes.
            return true;
        }

        $queue = $qi->transport;
        $object = unserialize($qi->frame);
        $this->_log(LOG_INFO, 'Got item id=' . $qi->id . ' for transport ' . $queue);

        // Yay! Got one!
        $handler = $this->getHandler($queue);
        if ($handler) {
            if ($handler->handle($object)) {
                $this->_log(LOG_INFO, "[$queue] Successfully handled object");
                $this->_done($qi);
            } else {
                $this->_log(LOG_INFO, "[$queue] Failed to handle object");
                $this->_fail($qi);
            }
        } else {
            $this->_log(LOG_INFO, "[$queue] No handler for queue $queue; discarding.");
            $this->_done($qi);
        }
        return true;
    }

    /**
     * Pop the oldest unclaimed item off the queue set and claim it.
     *
     * @return mixed false if no items; true if bogus hit; otherwise Queue_item
     */
    protected function _nextItem()
    {
        $start = time();
        $result = null;

        $qi = Queue_item::top();
        if (empty($qi)) {
            return false;
        }

        return $qi;
    }

    /**
     * Delete our claimed item from the queue after successful processing.
     *
     * @param QueueItem $qi
     */
    protected function _done($qi)
    {
        if (empty($qi)) {
            $this->_log(LOG_INFO, "_done passed an empty queue item");
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, "Reluctantly releasing unclaimed queue item");
            }
            $qi->delete();
            $qi->free();
        }

        $this->_log(LOG_INFO, "done with item");
    }

    /**
     * Free our claimed queue item for later reprocessing in case of
     * temporary failure.
     *
     * @param QueueItem $qi
     */
    protected function _fail($qi)
    {
        if (empty($qi)) {
            $this->_log(LOG_INFO, "_fail passed an empty queue item");
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, "Ignoring failure for unclaimed queue item");
            } else {
                $orig = clone($qi);
                $qi->claimed = null;
                $qi->update($orig);
                $qi = null;
            }
        }

        $this->_log(LOG_INFO, "done with queue item");
    }

    protected function _log($level, $msg)
    {
        common_log($level, 'DBQueueManager: '.$msg);
    }
}
