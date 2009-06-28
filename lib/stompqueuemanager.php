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

require_once 'Stomp.php';

class QueueManager
{
    var $server = null;
    var $username = null;
    var $password = null;
    var $base = null;
    var $con = null;
    var $frames = array();

    function __construct()
    {
        $this->server   = common_config('queue', 'stomp_server');
        $this->username = common_config('queue', 'stomp_username');
        $this->password = common_config('queue', 'stomp_password');
        $this->base     = common_config('queue', 'queue_basename');
    }

    function _connect()
    {
        if (empty($this->con)) {
            $this->con = new Stomp($this->server);

            if (!$this->con->connect($this->username, $this->password)) {
                $this->_log(LOG_ERR, 'Failed to connect to queue server');
                throw new ServerException('Failed to connect to queue server');
            }
        }
    }

    function enqueue($object, $queue)
    {
        $notice = (Notice)$object;

        $this->_connect();

        $result = $this->con->send($this->_queueName($queue),
                                   $notice->id, 		// BODY of the message
                                   array ('created' => $notice->created));

        if (!$result) {
            common_log(LOG_ERR, 'Error sending to '.$transport.' queue');
            return false;
        }

        common_log(LOG_DEBUG, 'complete remote queueing notice ID = '
                   . $notice->id . ' for ' . $transport);
    }

    function nextItem($queue, $timeout=null)
    {
        $result = null;

        $this->_connect();

        $frame = $this->con->readFrame();

        if ($frame) {
            $this->log(LOG_INFO, 'Got item enqueued '.common_exact_date($frame->headers['created']));

            // XXX: Now the queue handler receives only the ID of the
            // notice, and it has to get it from the DB
            // A massive improvement would be avoid DB query by transmitting
            // all the notice details via queue server...
            $notice = Notice::staticGet($frame->body);

            if ($notice) {
            } else {
                $this->log(LOG_WARNING, 'queue item for notice that does not exist');
            }
        }
    }

    function done($object, $queue)
    {
        $notice = (Notice)$object;

        $this->_connect();

        $frame = $this->_getFrame($notice, $queue);

        if (empty($frame)) {
            $this->log(LOG_ERR, 'Cannot find frame for notice '.$notice->id.' in queue '.$queue);
        } else {
            // if the msg has been handled positively, ack it
            // and the queue server will remove it from the queue
            $this->con->ack($frame);
            $this->log(LOG_INFO, 'finished broadcasting notice ID = ' . $notice->id);
        }
    }
}
