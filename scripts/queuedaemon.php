#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'fi:at:';
$longoptions = array('id=', 'foreground', 'all', 'threads=');

/**
 * Attempts to get a count of the processors available on the current system
 * to fan out multiple threads.
 *
 * Recognizes Linux and Mac OS X; others will return default of 1.
 *
 * @return intval
 */
function getProcessorCount()
{
    $cpus = 0;
    switch (PHP_OS) {
    case 'Linux':
        $cpuinfo = file('/proc/cpuinfo');
        foreach (file('/proc/cpuinfo') as $line) {
            if (preg_match('/^processor\s+:\s+(\d+)\s?$/', $line)) {
                $cpus++;
            }
        }
        break;
    case 'Darwin':
        $cpus = intval(shell_exec("/usr/sbin/sysctl -n hw.ncpu 2>/dev/null"));
        break;
    }
    if ($cpus) {
        return $cpus;
    }
    return 1;
}

$threads = getProcessorCount();
$helptext = <<<END_OF_QUEUE_HELP
Daemon script for running queued items.

    -i --id           Identity (default none)
    -f --foreground   Stay in the foreground (default background)
    -a --all          Handle queues for all local sites
                      (requires Stomp queue handler, status_network setup)
    -t --threads=<n>  Spawn <n> processing threads (default $threads)


END_OF_QUEUE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once(INSTALLDIR.'/lib/daemon.php');
require_once(INSTALLDIR.'/classes/Queue_item.php');
require_once(INSTALLDIR.'/classes/Notice.php');

define('CLAIM_TIMEOUT', 1200);

/**
 * Queue handling daemon...
 *
 * The queue daemon by default launches in the background, at which point
 * it'll pass control to the configured QueueManager class to poll for updates.
 *
 * We can then pass individual items through the QueueHandler subclasses
 * they belong to.
 */
class QueueDaemon extends Daemon
{
    protected $allsites;
    protected $threads=1;

    function __construct($id=null, $daemonize=true, $threads=1, $allsites=false)
    {
        parent::__construct($daemonize);

        if ($id) {
            $this->set_id($id);
        }
        $this->all = $allsites;
        $this->threads = $threads;
    }

    /**
     * How many seconds a polling-based queue manager should wait between
     * checks for new items to handle.
     *
     * Defaults to 60 seconds; override to speed up or slow down.
     *
     * @return int timeout in seconds
     */
    function timeout()
    {
        return 60;
    }

    function name()
    {
        return strtolower(get_class($this).'.'.$this->get_id());
    }

    function run()
    {
        if ($this->threads > 1) {
            return $this->runThreads();
        } else {
            return $this->runLoop();
        }
    }
    
    function runThreads()
    {
        $children = array();
        for ($i = 1; $i <= $this->threads; $i++) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                print "Couldn't fork for thread $i; aborting\n";
                exit(1);
            } else if ($pid == 0) {
                $this->runChild($i);
                exit(0);
            } else {
                $this->log(LOG_INFO, "Spawned thread $i as pid $pid");
                $children[$i] = $pid;
            }
        }
        
        $this->log(LOG_INFO, "Waiting for children to complete.");
        while (count($children) > 0) {
            $status = null;
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $i = array_search($pid, $children);
                if ($i === false) {
                    $this->log(LOG_ERR, "Unrecognized child pid $pid exited!");
                    continue;
                }
                unset($children[$i]);
                $this->log(LOG_INFO, "Thread $i pid $pid exited.");
                
                $pid = pcntl_fork();
                if ($pid < 0) {
                    print "Couldn't fork to respawn thread $i; aborting thread.\n";
                } else if ($pid == 0) {
                    $this->runChild($i);
                    exit(0);
                } else {
                    $this->log(LOG_INFO, "Respawned thread $i as pid $pid");
                    $children[$i] = $pid;
                }
            }
        }
        $this->log(LOG_INFO, "All child processes complete.");
        return true;
    }

    function runChild($thread)
    {
        $this->set_id($this->get_id() . "." . $thread);
        $this->resetDb();
        $this->runLoop();
    }

    /**
     * Reconnect to the database for each child process,
     * or they'll get very confused trying to use the
     * same socket.
     */
    function resetDb()
    {
        // @fixme do we need to explicitly open the db too
        // or is this implied?
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        // Reconnect main memcached, or threads will stomp on
        // each other and corrupt their requests.
        $cache = common_memcache();
        if ($cache) {
            $cache->reconnect();
        }

        // Also reconnect memcached for status_network table.
        if (!empty(Status_network::$cache)) {
            Status_network::$cache->close();
            Status_network::$cache = null;
        }
    }

    /**
     * Setup and start of run loop for this queue handler as a daemon.
     * Most of the heavy lifting is passed on to the QueueManager's service()
     * method, which passes control on to the QueueHandler's handle_notice()
     * method for each notice that comes in on the queue.
     *
     * Most of the time this won't need to be overridden in a subclass.
     *
     * @return boolean true on success, false on failure
     */
    function runLoop()
    {
        $this->log(LOG_INFO, 'checking for queued notices');

        $master = new IoMaster($this->get_id());
        $master->init($this->all);
        $master->service();

        $this->log(LOG_INFO, 'finished servicing the queue');

        $this->log(LOG_INFO, 'terminating normally');

        return true;
    }

    function log($level, $msg)
    {
        common_log($level, get_class($this) . ' ('. $this->get_id() .'): '.$msg);
    }
}

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

if (have_option('t')) {
    $threads = intval(get_option_value('t'));
} else if (have_option('--threads')) {
    $threads = intval(get_option_value('--threads'));
} else {
    $threads = 0;
}
if (!$threads) {
    $threads = getProcessorCount();
}

$daemonize = !(have_option('f') || have_option('--foreground'));
$all = have_option('a') || have_option('--all');

$daemon = new QueueDaemon($id, $daemonize, $threads, $all);
$daemon->runOnce();

