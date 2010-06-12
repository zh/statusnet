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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * AIM background connection manager for AIM-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has XMPP enabled.
 */

class MsnManager extends ImManager
{

    public $conn = null;
    /**
     * Initialize connection to server.
     * @return boolean true on success
     */
    public function start($master)
    {
        if(parent::start($master))
        {
            $this->connect();
            return true;
        }else{
            return false;
        }
    }

    public function getSockets()
    {
        $this->connect();
        if($this->conn){
            return $this->conn->getSockets();
        }else{
            return array();
        }
    }

    /**
     * Process AIM events that have come in over the wire.
     * @param resource $socket
     */
    public function handleInput($socket)
    {
        common_log(LOG_DEBUG, "Servicing the MSN queue.");
        $this->stats('msn_process');
        $this->conn->receive();
    }

    function connect()
    {
        if (!$this->conn) {
            $this->conn=new MSN(array(
                                      'user' => $this->plugin->user,
                                      'password' => $this->plugin->password,
                                      'alias' => $this->plugin->nickname,
                                      'psm' => 'Send me a message to post a notice',
                                      'debug' => true
                                )
                        );
            $this->conn->registerHandler("IMIn", array($this, 'handle_msn_message'));
            $this->conn->signon();
        }
        return $this->conn;
    }

    function handle_msn_message($data)
    {
        $this->plugin->enqueue_incoming_raw($data);
        return true;
    }

    function send_raw_message($data)
    {
        $this->connect();
        if (!$this->conn) {
            return false;
        }
        $this->conn->sflapSend($data[0],$data[1],$data[2],$data[3]);
        return true;
    }
}
