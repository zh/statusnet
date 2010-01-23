<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * I/O manager to wrap around socket-reading and polling queue & connection managers.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

abstract class IoMaster
{
    public $id;

    protected $multiSite = false;
    protected $managers = array();
    protected $singletons = array();

    protected $pollTimeouts = array();
    protected $lastPoll = array();

    /**
     * @param string $id process ID to use in logging/monitoring
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->monitor = new QueueMonitor();
    }

    public function init($multiSite=null)
    {
        if ($multiSite !== null) {
            $this->multiSite = $multiSite;
        }
        if ($this->multiSite) {
            $this->sites = $this->findAllSites();
        } else {
            $this->sites = array(common_config('site', 'server'));
        }

        if (empty($this->sites)) {
            throw new Exception("Empty status_network table, cannot init");
        }

        foreach ($this->sites as $site) {
            if ($site != common_config('site', 'server')) {
                StatusNet::init($site);
            }
            $this->initManagers();
        }
    }

    /**
     * Initialize IoManagers for the currently configured site
     * which are appropriate to this instance.
     *
     * Pass class names into $this->instantiate()
     */
    abstract function initManagers();

    /**
     * Pull all local sites from status_network table.
     * @return array of hostnames
     */
    protected function findAllSites()
    {
        $hosts = array();
        $sn = new Status_network();
        $sn->find();
        while ($sn->fetch()) {
            $hosts[] = $sn->hostname;
        }
        return $hosts;
    }

    /**
     * Instantiate an i/o manager class for the current site.
     * If a multi-site capable handler is already present,
     * we don't need to build a new one.
     *
     * @param string $class
     */
    protected function instantiate($class)
    {
        if (is_string($class) && isset($this->singletons[$class])) {
            // Already instantiated a multi-site-capable handler.
            // Just let it know it should listen to this site too!
            $this->singletons[$class]->addSite(common_config('site', 'server'));
            return;
        }

        $manager = $this->getManager($class);

        if ($this->multiSite) {
            $caps = $manager->multiSite();
            if ($caps == IoManager::SINGLE_ONLY) {
                throw new Exception("$class can't run with --all; aborting.");
            }
            if ($caps == IoManager::INSTANCE_PER_PROCESS) {
                // Save this guy for later!
                // We'll only need the one to cover multiple sites.
                $this->singletons[$class] = $manager;
                $manager->addSite(common_config('site', 'server'));
            }
        }

        $this->managers[] = $manager;
    }
    
    protected function getManager($class)
    {
        if(is_object($class)){
            return $class;
        } else {
            return call_user_func(array($class, 'get'));
        }
    }

    /**
     * Basic run loop...
     *
     * Initialize all io managers, then sit around waiting for input.
     * Between events or timeouts, pass control back to idle() method
     * to allow for any additional background processing.
     */
    function service()
    {
        $this->logState('init');
        $this->start();

        while (true) {
            $timeouts = array_values($this->pollTimeouts);
            $timeouts[] = 60; // default max timeout

            // Wait for something on one of our sockets
            $sockets = array();
            $managers = array();
            foreach ($this->managers as $manager) {
                foreach ($manager->getSockets() as $socket) {
                    $sockets[] = $socket;
                    $managers[] = $manager;
                }
                $timeouts[] = intval($manager->timeout());
            }

            $timeout = min($timeouts);
            if ($sockets) {
                $read = $sockets;
                $write = array();
                $except = array();
                $this->logState('listening');
                common_log(LOG_DEBUG, "Waiting up to $timeout seconds for socket data...");
                $ready = stream_select($read, $write, $except, $timeout, 0);

                if ($ready === false) {
                    common_log(LOG_ERR, "Error selecting on sockets");
                } else if ($ready > 0) {
                    foreach ($read as $socket) {
                        $index = array_search($socket, $sockets, true);
                        if ($index !== false) {
                            $this->logState('queue');
                            $managers[$index]->handleInput($socket);
                        } else {
                            common_log(LOG_ERR, "Saw input on a socket we didn't listen to");
                        }
                    }
                }
            }

            if ($timeout > 0 && empty($sockets)) {
                // If we had no listeners, sleep until the pollers' next requested wakeup.
                common_log(LOG_DEBUG, "Sleeping $timeout seconds until next poll cycle...");
                $this->logState('sleep');
                sleep($timeout);
            }

            $this->logState('poll');
            $this->poll();

            $this->logState('idle');
            $this->idle();

            $memoryLimit = $this->softMemoryLimit();
            if ($memoryLimit > 0) {
                $usage = memory_get_usage();
                if ($usage > $memoryLimit) {
                    common_log(LOG_INFO, "Queue thread hit soft memory limit ($usage > $memoryLimit); gracefully restarting.");
                    break;
                } else if (common_config('queue', 'debug_memory')) {
                    common_log(LOG_DEBUG, "Memory usage $usage");
                }
            }
        }

        $this->logState('shutdown');
        $this->finish();
    }

    /**
     * Return fully-parsed soft memory limit in bytes.
     * @return intval 0 or -1 if not set
     */
    function softMemoryLimit()
    {
        $softLimit = trim(common_config('queue', 'softlimit'));
        if (substr($softLimit, -1) == '%') {
            $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
            if ($limit > 0) {
                return intval(substr($softLimit, 0, -1) * $limit / 100);
            } else {
                return -1;
            }
        } else {
            return $this->parseMemoryLimit($softLimit);
        }
        return $softLimit;
    }

    /**
     * Interpret PHP shorthand for memory_limit and friends.
     * Why don't they just expose the actual numeric value? :P
     * @param string $mem
     * @return int
     */
    public function parseMemoryLimit($mem)
    {
        // http://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
        $mem = strtolower(trim($mem));
        $size = array('k' => 1024,
                      'm' => 1024*1024,
                      'g' => 1024*1024*1024);
        if (empty($mem)) {
            return 0;
        } else if (is_numeric($mem)) {
            return intval($mem);
        } else {
            $mult = substr($mem, -1);
            if (isset($size[$mult])) {
                return substr($mem, 0, -1) * $size[$mult];
            } else {
                return intval($mem);
            }
        }
    }

    function start()
    {
        foreach ($this->managers as $index => $manager) {
            $manager->start($this);
            // @fixme error check
            if ($manager->pollInterval()) {
                // We'll want to check for input on the first pass
                $this->pollTimeouts[$index] = 0;
                $this->lastPoll[$index] = 0;
            }
        }
    }

    function finish()
    {
        foreach ($this->managers as $manager) {
            $manager->finish();
            // @fixme error check
        }
    }

    /**
     * Called during the idle portion of the runloop to see which handlers
     */
    function poll()
    {
        foreach ($this->managers as $index => $manager) {
            $interval = $manager->pollInterval();
            if ($interval <= 0) {
                // Not a polling manager.
                continue;
            }

            if (isset($this->pollTimeouts[$index])) {
                $timeout = $this->pollTimeouts[$index];
                if (time() - $this->lastPoll[$index] < $timeout) {
                    // Not time to poll yet.
                    continue;
                }
            } else {
                $timeout = 0;
            }
            $hit = $manager->poll();

            $this->lastPoll[$index] = time();
            if ($hit) {
                // Do the next poll quickly, there may be more input!
                $this->pollTimeouts[$index] = 0;
            } else {
                // Empty queue. Exponential backoff up to the maximum poll interval.
                if ($timeout > 0) {
                    $timeout = min($timeout * 2, $interval);
                } else {
                    $timeout = 1;
                }
                $this->pollTimeouts[$index] = $timeout;
            }
        }
    }

    /**
     * Called after each handled item or empty polling cycle.
     * This is a good time to e.g. service your XMPP connection.
     */
    function idle()
    {
        foreach ($this->managers as $manager) {
            $manager->idle();
        }
    }

    /**
     * Send thread state update to the monitoring server, if configured.
     *
     * @param string $state ('init', 'queue', 'shutdown' etc)
     * @param string $substate (optional, eg queue name 'omb' 'sms' etc)
     */
    protected function logState($state, $substate='')
    {
        $this->monitor->logState($this->id, $state, $substate);
    }

    /**
     * Send thread stats.
     * Thread ID will be implicit; other owners can be listed as well
     * for per-queue and per-site records.
     *
     * @param string $key counter name
     * @param array $owners list of owner keys like 'queue:xmpp' or 'site:stat01'
     */
    public function stats($key, $owners=array())
    {
        $owners[] = "thread:" . $this->id;
        $this->monitor->stats($key, $owners);
    }
}

