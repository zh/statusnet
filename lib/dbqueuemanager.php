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
     * Saves a notice object reference into the queue item table.
     * @return boolean true on success
     * @throws ServerException on failure
     */
    public function enqueue($object, $queue)
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
        $this->_log(LOG_DEBUG, 'Checking for notices...');
        $item = $this->_nextItem();
        if ($item === false) {
            $this->_log(LOG_DEBUG, 'No notices waiting; idling.');
            return false;
        }
        if ($item === true) {
            // We dequeued an entry for a deleted or invalid notice.
            // Consider it a hit for poll rate purposes.
            return true;
        }

        list($queue, $notice) = $item;
        $this->_log(LOG_INFO, 'Got notice '. $notice->id . ' for transport ' . $queue);

        // Yay! Got one!
        $handler = $this->getHandler($queue);
        if ($handler) {
            if ($handler->handle_notice($notice)) {
                $this->_log(LOG_INFO, "[$queue:notice $notice->id] Successfully handled notice");
                $this->_done($notice, $queue);
            } else {
                $this->_log(LOG_INFO, "[$queue:notice $notice->id] Failed to handle notice");
                $this->_fail($notice, $queue);
            }
        } else {
            $this->_log(LOG_INFO, "[$queue:notice $notice->id] No handler for queue $queue");
            $this->_fail($notice, $queue);
        }
        return true;
    }

    /**
     * Pop the oldest unclaimed item off the queue set and claim it.
     *
     * @return mixed false if no items; true if bogus hit; otherwise array(string, Notice)
     *               giving the queue transport name.
     */
    protected function _nextItem()
    {
        $start = time();
        $result = null;

        $qi = Queue_item::top();
        if (empty($qi)) {
            return false;
        }

        $queue = $qi->transport;
        $notice = Notice::staticGet('id', $qi->notice_id);
        if (empty($notice)) {
            $this->_log(LOG_INFO, "[$queue:notice $notice->id] dequeued non-existent notice");
            $qi->delete();
            return true;
        }

        $result = $notice;
        return array($queue, $notice);
    }

    /**
     * Delete our claimed item from the queue after successful processing.
     *
     * @param Notice $object
     * @param string $queue
     */
    protected function _done($object, $queue)
    {
        // XXX: right now, we only handle notices

        $notice = $object;

        $qi = Queue_item::pkeyGet(array('notice_id' => $notice->id,
                                        'transport' => $queue));

        if (empty($qi)) {
            $this->_log(LOG_INFO, "[$queue:notice $notice->id] Cannot find queue item");
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, "[$queue:notice $notice->id] Reluctantly releasing unclaimed queue item");
            }
            $qi->delete();
            $qi->free();
        }

        $this->_log(LOG_INFO, "[$queue:notice $notice->id] done with item");
        $this->stats('handled', $queue);

        $notice->free();
    }

    /**
     * Free our claimed queue item for later reprocessing in case of
     * temporary failure.
     *
     * @param Notice $object
     * @param string $queue
     */
    protected function _fail($object, $queue)
    {
        // XXX: right now, we only handle notices

        $notice = $object;

        $qi = Queue_item::pkeyGet(array('notice_id' => $notice->id,
                                        'transport' => $queue));

        if (empty($qi)) {
            $this->_log(LOG_INFO, "[$queue:notice $notice->id] Cannot find queue item");
        } else {
            if (empty($qi->claimed)) {
                $this->_log(LOG_WARNING, "[$queue:notice $notice->id] Ignoring failure for unclaimed queue item");
            } else {
                $orig = clone($qi);
                $qi->claimed = null;
                $qi->update($orig);
                $qi = null;
            }
        }

        $this->_log(LOG_INFO, "[$queue:notice $notice->id] done with queue item");
        $this->stats('error', $queue);

        $notice->free();
    }

    protected function _log($level, $msg)
    {
        common_log($level, 'DBQueueManager: '.$msg);
    }
}
