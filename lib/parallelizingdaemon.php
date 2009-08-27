<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for making daemons that can do several tasks in parallel.
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
 * @category  Daemon
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

declare(ticks = 1);

/**
 * Daemon able to spawn multiple child processes to do work in parallel
 *
 * @category Daemon
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ParallelizingDaemon extends Daemon
{
    private $_children     = array();
    private $_interval     = 0; // seconds
    private $_max_children = 0; // maximum number of children
    private $_debug        = false;

    /**
     *  Constructor
     *
     * @param string  $id           the name/id of this daemon
     * @param int     $interval     sleep this long before doing everything again
     * @param int     $max_children maximum number of child processes at a time
     * @param boolean $debug        debug output flag
     *
     * @return void
     *
     **/

    function __construct($id = null, $interval = 60, $max_children = 2,
                         $debug = null)
    {
        parent::__construct(true); // daemonize

        $this->_interval     = $interval;
        $this->_max_children = $max_children;
        $this->_debug        = $debug;

        if (isset($id)) {
            $this->set_id($id);
        }
    }

    /**
     * Run the daemon
     *
     * @return void
     */

    function run()
    {
        if (isset($this->_debug)) {
            echo $this->name() . " - Debugging output enabled.\n";
        }

        do {

            $objects = $this->getObjects();

            foreach ($objects as $o) {

                // Fork a child for each object

                $pid = pcntl_fork();

                if ($pid == -1) {
                    die ($this->name() . ' - Couldn\'t fork!');
                }

                if ($pid) {

                    // Parent

                    if (isset($this->_debug)) {
                        echo $this->name() .
                          " - Forked new child - pid $pid.\n";

                    }

                    $this->_children[] = $pid;

                } else {

                    // Child

                    // Do something with each object

                    $this->childTask($o);

                    exit();
                }

                // Remove child from ps list as it finishes

                while (($c = pcntl_wait($status, WNOHANG OR WUNTRACED)) > 0) {

                    if (isset($this->_debug)) {
                        echo $this->name() . " - Child $c finished.\n";
                    }

                    $this->removePs($this->_children, $c);
                }

                // Wait! We have too many damn kids.

                if (sizeof($this->_children) >= $this->_max_children) {

                    if (isset($this->_debug)) {
                        echo $this->name() . " - Too many children. Waiting...\n";
                    }

                    if (($c = pcntl_wait($status, WUNTRACED)) > 0) {

                        if (isset($this->_debug)) {
                            echo $this->name() .
                              " - Finished waiting for child $c.\n";
                        }

                        $this->removePs($this->_children, $c);
                    }
                }
            }

            // Remove all children from the process list before restarting
            while (($c = pcntl_wait($status, WUNTRACED)) > 0) {

                if (isset($this->_debug)) {
                    echo $this->name() . " - Child $c finished.\n";
                }

                $this->removePs($this->_children, $c);
            }

            // Rest for a bit

            if (isset($this->_debug)) {
                echo $this->name() . ' - Waiting ' . $this->_interval .
                  " secs before running again.\n";
            }

            if ($this->_interval > 0) {
                sleep($this->_interval);
            }

        } while (true);
    }

    /**
     * Remove a child process from the list of children
     *
     * @param array &$plist array of processes
     * @param int   $ps     process id
     *
     * @return void
     */

    function removePs(&$plist, $ps)
    {
        for ($i = 0; $i < sizeof($plist); $i++) {
            if ($plist[$i] == $ps) {
                unset($plist[$i]);
                $plist = array_values($plist);
                break;
            }
        }
    }

    /**
     * Get a list of objects to work on in parallel
     *
     * @return array An array of objects to work on
     */

    function getObjects()
    {
        die('Implement ParallelizingDaemon::getObjects().');
    }

    /**
     * Do something with each object in parallel
     *
     * @param mixed $object data to work on
     *
     * @return void
     */

    function childTask($object)
    {
        die("Implement ParallelizingDaemon::childTask($object).");
    }

}