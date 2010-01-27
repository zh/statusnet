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
    protected $control;
    
    protected $sites = array();
    protected $subscriptions = array();

    protected $useTransactions = true;
    protected $transaction = null;
    protected $transactionCount = 0;

    function __construct()
    {
        parent::__construct();
        $this->server   = common_config('queue', 'stomp_server');
        $this->username = common_config('queue', 'stomp_username');
        $this->password = common_config('queue', 'stomp_password');
        $this->base     = common_config('queue', 'queue_basename');
        $this->control  = common_config('queue', 'control_channel');
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
     * Optional; ping any running queue handler daemons with a notification
     * such as announcing a new site to handle or requesting clean shutdown.
     * This avoids having to restart all the daemons manually to update configs
     * and such.
     *
     * Currently only relevant for multi-site queue managers such as Stomp.
     *
     * @param string $event event key
     * @param string $param optional parameter to append to key
     * @return boolean success
     */
    public function sendControlSignal($event, $param='')
    {
        $message = $event;
        if ($param != '') {
            $message .= ':' . $param;
        }
        $this->_connect();
        $result = $this->con->send($this->control,
                                   $message,
                                   array ('created' => common_sql_now()));
        if ($result) {
            $this->_log(LOG_INFO, "Sent control ping to queue daemons: $message");
            return true;
        } else {
            $this->_log(LOG_ERR, "Failed sending control ping to queue daemons: $message");
            return false;
        }
    }

    /**
     * Instantiate the appropriate QueueHandler class for the given queue.
     *
     * @param string $queue
     * @return mixed QueueHandler or null
     */
    function getHandler($queue)
    {
        $handlers = $this->handlers[$this->currentSite()];
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
        $group = $this->activeGroup();
        $site = $this->currentSite();
        if (empty($this->groups[$site][$group])) {
            return array();
        } else {
            return array_keys($this->groups[$site][$group]);
        }
    }

    /**
     * Register a queue transport name and handler class for your plugin.
     * Only registered transports will be reliably picked up!
     *
     * @param string $transport
     * @param string $class
     * @param string $group
     */
    public function connect($transport, $class, $group='queuedaemon')
    {
        $this->handlers[$this->currentSite()][$transport] = $class;
        $this->groups[$this->currentSite()][$group][$transport] = $class;
    }

    /**
     * Saves a notice object reference into the queue item table.
     * @return boolean true on success
     */
    public function enqueue($object, $queue)
    {
        $msg = $this->encode($object);
        $rep = $this->logrep($object);

        $this->_connect();

        // XXX: serialize and send entire notice

        $result = $this->con->send($this->queueName($queue),
                                   $msg, 		// BODY of the message
                                   array ('created' => common_sql_now(),
                                          'persistent' => 'true'));

        if (!$result) {
            common_log(LOG_ERR, "Error sending $rep to $queue queue");
            return false;
        }

        common_log(LOG_DEBUG, "complete remote queueing $rep for $queue");
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
            $dest = $frame->headers['destination'];
            if ($dest == $this->control) {
                if (!$this->handleControlSignal($frame)) {
                    // We got a control event that requests a shutdown;
                    // close out and stop handling anything else!
                    break;
                }
            } else {
                $ok = $ok && $this->handleItem($frame);
            }
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
        $this->_connect();

        $this->con->subscribe($this->control);
        if ($this->sites) {
            foreach ($this->sites as $server) {
                StatusNet::init($server);
                $this->doSubscribe();
            }
        } else {
            $this->doSubscribe();
        }
        $this->begin();
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
        // If there are any outstanding delivered messages we haven't processed,
        // free them for another thread to take.
        $this->rollback();
        $this->con->unsubscribe($this->control);
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
     * Get identifier of the currently active site configuration
     * @return string
     */
    protected function currentSite()
    {
        return common_config('site', 'server'); // @fixme switch to nickname
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
        $site = $this->currentSite();
        $this->_connect();
        foreach ($this->getQueues() as $queue) {
            $rawqueue = $this->queueName($queue);
            $this->subscriptions[$site][$queue] = $rawqueue;
            $this->_log(LOG_INFO, "Subscribing to $rawqueue");
            $this->con->subscribe($rawqueue);
        }
    }

    /**
     * Subscribe from all enabled notice queues for the current site.
     */
    protected function doUnsubscribe()
    {
        $site = $this->currentSite();
        $this->_connect();
        if (!empty($this->subscriptions[$site])) {
            foreach ($this->subscriptions[$site] as $queue => $rawqueue) {
                $this->_log(LOG_INFO, "Unsubscribing from $rawqueue");
                $this->con->unsubscribe($rawqueue);
                unset($this->subscriptions[$site][$queue]);
            }
        }
    }

    /**
     * Handle and acknowledge an event that's come in through a queue.
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
    protected function handleItem($frame)
    {
        list($site, $queue) = $this->parseDestination($frame->headers['destination']);
        if ($site != $this->currentSite()) {
            $this->stats('switch');
            StatusNet::init($site);
        }

        if (is_numeric($frame->body)) {
            $id = intval($frame->body);
            $info = "notice $id posted at {$frame->headers['created']} in queue $queue";

            $notice = Notice::staticGet('id', $id);
            if (empty($notice)) {
                $this->_log(LOG_WARNING, "Skipping missing $info");
                $this->ack($frame);
                $this->commit();
                $this->begin();
                $this->stats('badnotice', $queue);
                return false;
            }

            $item = $notice;
        } else {
            // @fixme should we serialize, or json, or what here?
            $info = "string posted at {$frame->headers['created']} in queue $queue";
            $item = $frame->body;
        }

        $handler = $this->getHandler($queue);
        if (!$handler) {
            $this->_log(LOG_ERR, "Missing handler class; skipping $info");
            $this->ack($frame);
            $this->commit();
            $this->begin();
            $this->stats('badhandler', $queue);
            return false;
        }

        $ok = $handler->handle($item);

        if (!$ok) {
            $this->_log(LOG_WARNING, "Failed handling $info");
            // FIXME we probably shouldn't have to do
            // this kind of queue management ourselves;
            // if we don't ack, it should resend...
            $this->ack($frame);
            $this->enqueue($item, $queue);
            $this->commit();
            $this->begin();
            $this->stats('requeued', $queue);
            return false;
        }

        $this->_log(LOG_INFO, "Successfully handled $info");
        $this->ack($frame);
        $this->commit();
        $this->begin();
        $this->stats('handled', $queue);
        return true;
    }

    /**
     * Process a control signal broadcast.
     *
     * @param array $frame Stomp frame
     * @return bool true to continue; false to stop further processing.
     */
    protected function handleControlSignal($frame)
    {
        $message = trim($frame->body);
        if (strpos($message, ':') !== false) {
            list($event, $param) = explode(':', $message, 2);
        } else {
            $event = $message;
            $param = '';
        }

        $shutdown = false;

        if ($event == 'shutdown') {
            $this->master->requestShutdown();
            $shutdown = true;
        } else if ($event == 'restart') {
            $this->master->requestRestart();
            $shutdown = true;
        } else if ($event == 'update') {
            $this->updateSiteConfig($param);
        } else {
            $this->_log(LOG_ERR, "Ignoring unrecognized control message: $message");
        }

        $this->ack($frame);
        $this->commit();
        $this->begin();
        return $shutdown;
    }
    
    /**
     * Set us up with queue subscriptions for a new site added at runtime,
     * triggered by a broadcast to the 'statusnet-control' topic.
     *
     * @param array $frame Stomp frame
     * @return bool true to continue; false to stop further processing.
     */
    protected function updateSiteConfig($nickname)
    {
        if (empty($this->sites)) {
            if ($nickname == common_config('site', 'nickname')) {
                StatusNet::init(common_config('site', 'server'));
                $this->doUnsubscribe();
                $this->doSubscribe();
            } else {
                $this->_log(LOG_INFO, "Ignoring update ping for other site $nickname");
            }
        } else {
            $sn = Status_network::staticGet($nickname);
            if ($sn) {
                $server = $sn->getServerName(); // @fixme do config-by-nick
                StatusNet::init($server);
                if (empty($this->sites[$server])) {
                    $this->addSite($server);
                }
                $this->_log(LOG_INFO, "(Re)subscribing to queues for site $nickname / $server");
                $this->doUnsubscribe();
                $this->doSubscribe();
                $this->stats('siteupdate');
            } else {
                $this->_log(LOG_ERR, "Ignoring ping for unrecognized new site $nickname");
            }
        }
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
            $this->currentSite() . '/' . $queue;
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

    protected function begin()
    {
        if ($this->useTransactions) {
            if ($this->transaction) {
                throw new Exception("Tried to start transaction in the middle of a transaction");
            }
            $this->transactionCount++;
            $this->transaction = $this->master->id . '-' . $this->transactionCount . '-' . time();
            $this->con->begin($this->transaction);
        }
    }

    protected function ack($frame)
    {
        if ($this->useTransactions) {
            if (!$this->transaction) {
                throw new Exception("Tried to ack but not in a transaction");
            }
        }
        $this->con->ack($frame, $this->transaction);
    }

    protected function commit()
    {
        if ($this->useTransactions) {
            if (!$this->transaction) {
                throw new Exception("Tried to commit but not in a transaction");
            }
            $this->con->commit($this->transaction);
            $this->transaction = null;
        }
    }

    protected function rollback()
    {
        if ($this->useTransactions) {
            if (!$this->transaction) {
                throw new Exception("Tried to rollback but not in a transaction");
            }
            $this->con->commit($this->transaction);
            $this->transaction = null;
        }
    }
}

