<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * i/o manager to watch for a dead parent process
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
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class ProcessManager extends IoManager
{
    protected $socket;

    public static function get()
    {
        throw new Exception("Must pass ProcessManager per-instance");
    }

    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    /**
     * Tell the i/o queue master if and how we can handle multi-site
     * processes.
     *
     * Return one of:
     *   IoManager::SINGLE_ONLY
     *   IoManager::INSTANCE_PER_SITE
     *   IoManager::INSTANCE_PER_PROCESS
     */
    public static function multiSite()
    {
        return IoManager::INSTANCE_PER_PROCESS;
    }

    /**
     * We won't get any input on it, but if it's broken we'll
     * know something's gone horribly awry.
     *
     * @return array of resources
     */
    function getSockets()
    {
        return array($this->socket);
    }

    /**
     * See if the parent died and request a shutdown...
     *
     * @param resource $socket
     * @return boolean success
     */
    function handleInput($socket)
    {
        if (feof($socket)) {
            common_log(LOG_INFO, "Parent process exited; shutting down child.");
            $this->master->requestShutdown();
        }
        return true;
    }
}

