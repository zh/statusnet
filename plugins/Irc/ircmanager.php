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
 * IRC background connection manager for IRC-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be handled.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has IRC enabled.
 */

class IrcManager extends ImManager {

    public $conn = null;
    /**
     * Initialize connection to server.
     * @return boolean true on success
     */
    public function start($master) {
        if (parent::start($master)) {
            $this->connect();
            return true;
        } else {
            return false;
        }
    }

    public function getSockets() {
        $this->connect();
        if ($this->conn) {
            return array($this->conn->myConnection);
        } else {
            return array();
        }
    }

    /**
     * Process IRC events that have come in over the wire.
     * @param resource $socket
     */
    public function handleInput($socket) {
        common_log(LOG_DEBUG, 'Servicing the IRC queue.');
        $this->stats('irc_process');
        $this->conn->receive();
    }

    function connect() {
        if (!$this->conn) {
            $this->conn = new Phergie_Extended_Bot;

            $password = isset($this->plugin->password) ? $this->plugin->password : '';
            $transport = isset($this->plugin->transport) ? $this->plugin->transport : 'tcp';
            $encoding = isset($this->plugin->encoding) ? $this->plugin->encoding : 'ISO-8859-1';
            $nickservpassword = isset($this->plugin->nickservpassword) ? $this->plugin->nickservpassword : '';
            $channels = isset($this->plugin->channels) ? $this->plugin->channels : array();

            $config = new Phergie_Extended_Config;
            $config->readArray(
                array(
                    // One array per connection, pretty self-explanatory
                    'connections' => array(
                        array(
                            'host' => $this->plugin->host,
                            'port' => $this->plugin->port,
                            'username' => $this->plugin->username,
                            'realname' => $this->plugin->realname,
                            'nick' => $this->plugin->nickname,
                            'password' => $password,
                            'transport' => $transport,
                            'encoding' => $encoding
                        )
                    ),

                    'driver' => 'statusnet',

                    'processor' => 'statusnet',

                    'plugins' => array(
                        'Pong',
                        'NickServ',
                        'AutoJoin',
                        'Statusnet_Callback',
                    ),

                    'plugins.autoload' => true,

                    'ui.enabled' => true,

                    'nickserv.password' => $nickservpassword,
                    'autojoin.channels' => $channels,
                    'statusnet_callback.callback' => array($this, 'handle_irc_message')
                )
            );

            $this->conn->setConfig($config);
            $this->conn->connect();
        }
        return $this->conn;
    }

    function handle_irc_message($data) {
        $this->plugin->enqueue_incoming_raw($data);
        return true;
    }

    function send_raw_message($data) {
        $this->connect();
        if (!$this->conn) {
            return false;
        }
        $this->conn->sflapSend($data[0],$data[1],$data[2],$data[3]);
        return true;
    }
}
