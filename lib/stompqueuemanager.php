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

class LiberalStomp extends Stomp
{
    function getSocket()
    {
        return $this->_socket;
    }
}

class StompQueueManager
{
    var $server = null;
    var $username = null;
    var $password = null;
    var $base = null;
    var $con = null;

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
            $this->_log(LOG_INFO, "Connecting to '$this->server' as '$this->username'...");
            $this->con = new LiberalStomp($this->server);

            if ($this->con->connect($this->username, $this->password)) {
                $this->_log(LOG_INFO, "Connected.");
            } else {
                $this->_log(LOG_ERR, 'Failed to connect to queue server');
                throw new ServerException('Failed to connect to queue server');
            }
        }
    }

    function enqueue($object, $queue)
    {
        $notice = $object;

        $this->_connect();

        // XXX: serialize and send entire notice

        $result = $this->con->send($this->_queueName($queue),
                                   $notice->id, 		// BODY of the message
                                   array ('created' => $notice->created));

        if (!$result) {
            common_log(LOG_ERR, 'Error sending to '.$queue.' queue');
            return false;
        }

        common_log(LOG_DEBUG, 'complete remote queueing notice ID = '
                   . $notice->id . ' for ' . $queue);
    }

    function service($queue, $handler)
    {
        $result = null;

        $this->_connect();

        $this->con->setReadTimeout($handler->timeout());

        $this->con->subscribe($this->_queueName($queue));

        while (true) {

            // Wait for something on one of our sockets

            $stompsock = $this->con->getSocket();

            $handsocks = $handler->getSockets();

            $socks = array_merge(array($stompsock), $handsocks);

            $read = $socks;
            $write = array();
            $except = array();

            $ready = stream_select($read, $write, $except, $handler->timeout(), 0);

            if ($ready === false) {
                $this->_log(LOG_ERR, "Error selecting on sockets");
            } else if ($ready > 0) {
                if (in_array($stompsock, $read)) {
                    $this->_handleNotice($queue, $handler);
                }
                $handler->idle(QUEUE_HANDLER_HIT_IDLE);
            }
        }

        $this->con->unsubscribe($this->_queueName($queue));
    }

    function _handleNotice($queue, $handler)
    {
        $frame = $this->con->readFrame();

        if (!empty($frame)) {
            $notice = Notice::staticGet('id', $frame->body);

            if (empty($notice)) {
                $this->_log(LOG_WARNING, 'Got ID '. $frame->body .' for non-existent notice in queue '. $queue);
                $this->con->ack($frame);
            } else {
                if ($handler->handle_notice($notice)) {
                    $this->_log(LOG_INFO, 'Successfully handled notice '. $notice->id .' posted at ' . $frame->headers['created'] . ' in queue '. $queue);
                    $this->con->ack($frame);
                } else {
                    $this->_log(LOG_WARNING, 'Failed handling notice '. $notice->id .' posted at ' . $frame->headers['created']  . ' in queue '. $queue);
                    // FIXME we probably shouldn't have to do
                    // this kind of queue management ourselves
                    $this->con->ack($frame);
                    $this->enqueue($notice, $queue);
                }
                unset($notice);
            }

            unset($frame);
        }
    }

    function _queueName($queue)
    {
        return common_config('queue', 'queue_basename') . $queue;
    }

    function _log($level, $msg)
    {
        common_log($level, 'StompQueueManager: '.$msg);
    }
}
