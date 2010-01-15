<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

require_once 'Stomp.php';


class StompQueueManager extends QueueManager
{
    var $server = null;
    var $username = null;
    var $password = null;
    var $base = null;
    var $con = null;
    
    protected $master = null;
    protected $sites = array();

    function __construct()
    {
        parent::__construct();
        $this->server   = common_config('queue', 'stomp_server');
        $this->username = common_config('queue', 'stomp_username');
        $this->password = common_config('queue', 'stomp_password');
        $this->base     = common_config('queue', 'queue_basename');
    }

    /**
     * Tell the i/o master we only need a single instance to cover
     * all sites running in this process.
     */
    public static function multiSite()
    {
        return IoManager::INSTANCE_PER_PROCESS;
    }

    /**
     * Record each site we'll be handling input for in this process,
     * so we can listen to the necessary queues for it.
     *
     * @fixme possibly actually do subscription here to save another
     *        loop over all sites later?
     * @fixme possibly don't assume it's the current site
     */
    public function addSite($server)
    {
        $this->sites[] = $server;
        $this->initialize();
    }


    /**
     * Instantiate the appropriate QueueHandler class for the given queue.
     *
     * @param string $queue
     * @return mixed QueueHandler or null
     */
    function getHandler($queue)
    {
        $handlers = $this->handlers[common_config('site', 'server')];
        if (isset($handlers[$queue])) {
            $class = $handlers[$queue];
            if (class_exists($class)) {
                return new $class();
            } else {
                common_log(LOG_ERR, "Nonexistent handler class '$class' for queue '$queue'");
            }
        } else {
            common_log(LOG_ERR, "Requested handler for unkown queue '$queue'");
        }
        return null;
    }

    /**
     * Get a list of all registered queue transport names.
     *
     * @return array of strings
     */
    function getQueues()
    {
        return array_keys($this->handlers[common_config('site', 'server')]);
    }

    /**
     * Register a queue transport name and handler class for your plugin.
     * Only registered transports will be reliably picked up!
     *
     * @param string $transport
     * @param string $class
     */
    public function connect($transport, $class)
    {
        $this->handlers[common_config('site', 'server')][$transport] = $class;
    }

    /**
     * Saves a notice object reference into the queue item table.
     * @return boolean true on success
     */
    public function enqueue($object, $queue)
    {
        $notice = $object;

        $this->_connect();

        // XXX: serialize and send entire notice

        $result = $this->con->send($this->queueName($queue),
                                   $notice->id, 		// BODY of the message
                                   array ('created' => $notice->created));

        if (!$result) {
            common_log(LOG_ERR, 'Error sending to '.$queue.' queue');
            return false;
        }

        common_log(LOG_DEBUG, 'complete remote queueing notice ID = '
                   . $notice->id . ' for ' . $queue);
        $this->stats('enqueued', $queue);
    }

    /**
     * Send any sockets we're listening on to the IO manager
     * to wait for input.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        return array($this->con->getSocket());
    }

    /**
     * We've got input to handle on our socket!
     * Read any waiting Stomp frame(s) and process them.
     *
     * @param resource $socket
     * @return boolean ok on success
     */
    public function handleInput($socket)
    {
        assert($socket === $this->con->getSocket());
        $ok = true;
        $frames = $this->con->readFrames();
        foreach ($frames as $frame) {
            $ok = $ok && $this->_handleNotice($frame);
        }
        return $ok;
    }

    /**
     * Initialize our connection and subscribe to all the queues
     * we're going to need to handle...
     *
     * Side effects: in multi-site mode, may reset site configuration.
     *
     * @param IoMaster $master process/event controller
     * @return bool return false on failure
     */
    public function start($master)
    {
        parent::start($master);
        if ($this->sites) {
            foreach ($this->sites as $server) {
                StatusNet::init($server);
                $this->doSubscribe();
            }
        } else {
            $this->doSubscribe();
        }
        return true;
    }
    
    /**
     * Subscribe to all the queues we're going to need to handle...
     *
     * Side effects: in multi-site mode, may reset site configuration.
     *
     * @return bool return false on failure
     */
    public function finish()
    {
        if ($this->sites) {
            foreach ($this->sites as $server) {
                StatusNet::init($server);
                $this->doUnsubscribe();
            }
        } else {
            $this->doUnsubscribe();
        }
        return true;
    }
    
    /**
     * Lazy open connection to Stomp queue server.
     */
    protected function _connect()
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

    /**
     * Subscribe to all enabled notice queues for the current site.
     */
    protected function doSubscribe()
    {
        $this->_connect();
        foreach ($this->getQueues() as $queue) {
            $rawqueue = $this->queueName($queue);
            $this->_log(LOG_INFO, "Subscribing to $rawqueue");
            $this->con->subscribe($rawqueue);
        }
    }
    
    /**
     * Subscribe from all enabled notice queues for the current site.
     */
    protected function doUnsubscribe()
    {
        $this->_connect();
        foreach ($this->getQueues() as $queue) {
            $this->con->unsubscribe($this->queueName($queue));
        }
    }

    /**
     * Handle and acknowledge a notice event that's come in through a queue.
     *
     * If the queue handler reports failure, the message is requeued for later.
     * Missing notices or handler classes will drop the message.
     *
     * Side effects: in multi-site mode, may reset site configuration to
     * match the site that queued the event.
     *
     * @param StompFrame $frame
     * @return bool
     */
    protected function _handleNotice($frame)
    {
        list($site, $queue) = $this->parseDestination($frame->headers['destination']);
        if ($site != common_config('site', 'server')) {
            $this->stats('switch');
            StatusNet::init($site);
        }

        $id = intval($frame->body);
        $info = "notice $id posted at {$frame->headers['created']} in queue $queue";

        $notice = Notice::staticGet('id', $id);
        if (empty($notice)) {
            $this->_log(LOG_WARNING, "Skipping missing $info");
            $this->con->ack($frame);
            $this->stats('badnotice', $queue);
            return false;
        }

        $handler = $this->getHandler($queue);
        if (!$handler) {
            $this->_log(LOG_ERROR, "Missing handler class; skipping $info");
            $this->con->ack($frame);
            $this->stats('badhandler', $queue);
            return false;
        }

        $ok = $handler->handle_notice($notice);

        if (!$ok) {
            $this->_log(LOG_WARNING, "Failed handling $info");
            // FIXME we probably shouldn't have to do
            // this kind of queue management ourselves;
            // if we don't ack, it should resend...
            $this->con->ack($frame);
            $this->enqueue($notice, $queue);
            $this->stats('requeued', $queue);
            return false;
        }

        $this->_log(LOG_INFO, "Successfully handled $info");
        $this->con->ack($frame);
        $this->stats('handled', $queue);
        return true;
    }

    /**
     * Combines the queue_basename from configuration with the
     * site server name and queue name to give eg:
     *
     * /queue/statusnet/identi.ca/sms
     *
     * @param string $queue
     * @return string
     */
    protected function queueName($queue)
    {
        return common_config('queue', 'queue_basename') .
            common_config('site', 'server') . '/' . $queue;
    }

    /**
     * Returns the site and queue name from the server-side queue.
     *
     * @param string queue destination (eg '/queue/statusnet/identi.ca/sms')
     * @return array of site and queue: ('identi.ca','sms') or false if unrecognized
     */
    protected function parseDestination($dest)
    {
        $prefix = common_config('queue', 'queue_basename');
        if (substr($dest, 0, strlen($prefix)) == $prefix) {
            $rest = substr($dest, strlen($prefix));
            return explode("/", $rest, 2);
        } else {
            common_log(LOG_ERR, "Got a message from unrecognized stomp queue: $dest");
            return array(false, false);
        }
    }

    function _log($level, $msg)
    {
        common_log($level, 'StompQueueManager: '.$msg);
    }
}

