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

define('CLAIM_TIMEOUT', 1200);

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/daemon.php');
require_once(INSTALLDIR.'/classes/Queue_item.php');
require_once(INSTALLDIR.'/classes/Notice.php');

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

    function db_dispatch() {
        do {
            $qi = Queue_item::top($this->transport());
            if ($qi) {
                $this->log(LOG_INFO, 'Got item enqueued '.common_exact_date($qi->created));
                $notice = Notice::staticGet($qi->notice_id);
                if ($notice) {
                    $this->log(LOG_INFO, 'broadcasting notice ID = ' . $notice->id);
                    # XXX: what to do if broadcast fails?
                    $result = $this->handle_notice($notice);
                    if (!$result) {
                        $this->log(LOG_WARNING, 'Failed broadcast for notice ID = ' . $notice->id);
                        $orig = $qi;
                        $qi->claimed = null;
                        $qi->update($orig);
                        $this->log(LOG_WARNING, 'Abandoned claim for notice ID = ' . $notice->id);
                        continue;
                    }
                    $this->log(LOG_INFO, 'finished broadcasting notice ID = ' . $notice->id);
                    $notice->free();
                    unset($notice);
                    $notice = null;
                } else {
                    $this->log(LOG_WARNING, 'queue item for notice that does not exist');
                }
                $qi->delete();
                $qi->free();
                unset($qi);
                $this->idle(0);
            } else {
                $this->clear_old_claims();
                $this->idle(5);
            }
        } while (true);
    }

    function stomp_dispatch() {

        // use an external message queue system via STOMP
        require_once("Stomp.php");

        $server = common_config('queue','stomp_server');
        $username = common_config('queue', 'stomp_username');
        $password = common_config('queue', 'stomp_password');

        $con = new Stomp($server);

        if (!$con->connect($username, $password)) {
            $this->log(LOG_ERR, 'Failed to connect to queue server');
            return false;
        }

        $queue_basename = common_config('queue','queue_basename');
        // subscribe to the relevant queue (format: basename-transport)
        $con->subscribe('/queue/'.$queue_basename.'-'.$this->transport());

        do {
            $frame = $con->readFrame();
            if ($frame) {
                $this->log(LOG_INFO, 'Got item enqueued '.common_exact_date($frame->headers['created']));

                // XXX: Now the queue handler receives only the ID of the
                // notice, and it has to get it from the DB
                // A massive improvement would be avoid DB query by transmitting
                // all the notice details via queue server...
                $notice = Notice::staticGet($frame->body);

                if ($notice) {
                    $this->log(LOG_INFO, 'broadcasting notice ID = ' . $notice->id);
                    $result = $this->handle_notice($notice);
                    if ($result) {
                        // if the msg has been handled positively, ack it
                        // and the queue server will remove it from the queue
                        $con->ack($frame);
                        $this->log(LOG_INFO, 'finished broadcasting notice ID = ' . $notice->id);
                    }
                    else {
                        // no ack
                        $this->log(LOG_WARNING, 'Failed broadcast for notice ID = ' . $notice->id);
                    }
                    $notice->free();
                    unset($notice);
                    $notice = null;
                } else {
                    $this->log(LOG_WARNING, 'queue item for notice that does not exist');
                }
            }
        } while (true);

        $con->disconnect();
    }

    function run()
    {
        if (!$this->start()) {
            return false;
        }
        $this->log(LOG_INFO, 'checking for queued notices');
        if (common_config('queue','subsystem') == 'stomp') {
            $this->stomp_dispatch();
        }
        else {
            $this->db_dispatch();
        }
        if (!$this->finish()) {
            return false;
        }
        return true;
    }

    function idle($timeout=0)
    {
        if ($timeout>0) {
            sleep($timeout);
        }
    }

    function clear_old_claims()
    {
        $qi = new Queue_item();
        $qi->transport = $this->transport();
        $qi->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
        $qi->update(DB_DATAOBJECT_WHEREADD_ONLY);
        $qi->free();
        unset($qi);
    }

    function log($level, $msg)
    {
        common_log($level, $this->class_name() . ' ('. $this->get_id() .'): '.$msg);
    }
}

