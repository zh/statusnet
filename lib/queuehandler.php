<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/daemon.php');
require_once(INSTALLDIR.'/classes/Queue_item.php');
require_once(INSTALLDIR.'/classes/Notice.php');

define('CLAIM_TIMEOUT', 1200);
define('QUEUE_HANDLER_MISS_IDLE', 10);
define('QUEUE_HANDLER_HIT_IDLE', 0);

class QueueHandler extends Daemon
{
    var $_id = 'generic';

    function __construct($id=null, $daemonize=true)
    {
        parent::__construct($daemonize);

        if ($id) {
            $this->set_id($id);
        }
    }

    function timeout()
    {
        return 60;
    }

    function class_name()
    {
        return ucfirst($this->transport()) . 'Handler';
    }

    function name()
    {
        return strtolower($this->class_name().'.'.$this->get_id());
    }

    function get_id()
    {
        return $this->_id;
    }

    function set_id($id)
    {
        $this->_id = $id;
    }

    function transport()
    {
        return null;
    }

    function start()
    {
    }

    function finish()
    {
    }

    function handle_notice($notice)
    {
        return true;
    }

    function run()
    {
        if (!$this->start()) {
            return false;
        }

        $this->log(LOG_INFO, 'checking for queued notices');

        $queue   = $this->transport();
        $timeout = $this->timeout();

        $qm = QueueManager::get();

        $qm->service($queue, $this);

        if (!$this->finish()) {
            return false;
        }
        return true;
    }

    function idle($timeout=0)
    {
        if ($timeout > 0) {
            sleep($timeout);
        }
    }

    function log($level, $msg)
    {
        common_log($level, $this->class_name() . ' ('. $this->get_id() .'): '.$msg);
    }

    function getSockets()
    {
        return array();
    }
}

