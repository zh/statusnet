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
    public $regchecks = array();
    public $regchecksLookup = array();

    /**
     * Initialize connection to server.
     *
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

    /**
    * Return any open sockets that the run loop should listen
    * for input on.
    *
    * @return array Array of socket resources
    */
    public function getSockets() {
        $this->connect();
        if ($this->conn) {
            return $this->conn->getSockets();
        } else {
            return array();
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     * Send keepalive pings to server.
     *
     * @return void
     */
    public function idle() {
        // Call Phergie's doTick methods if necessary
        echo "BEGIN IDLE\n";
        $this->conn->handleEvents();
        echo "END IDLE\n";
    }

    /**
     * Process IRC events that have come in over the wire.
     *
     * @param resource $socket
     * @return void
     */
    public function handleInput($socket) {
        common_log(LOG_DEBUG, 'Servicing the IRC queue.');
        $this->stats('irc_process');
        $this->conn->handleEvents();
    }

    /**
    * Initiate connection
    *
    * @return void
    */
    public function connect() {
        if (!$this->conn) {
            $this->conn = new Phergie_StatusnetBot;

            $config = new Phergie_Config;
            $config->readArray(
                array(
                    'connections' => array(
                        array(
                            'host' => $this->plugin->host,
                            'port' => $this->plugin->port,
                            'username' => $this->plugin->username,
                            'realname' => $this->plugin->realname,
                            'nick' => $this->plugin->nick,
                            'password' => $this->plugin->password,
                            'transport' => $this->plugin->transporttype,
                            'encoding' => $this->plugin->encoding
                        )
                    ),

                    'driver' => 'statusnet',

                    'processor' => 'async',
                    'processor.options' => array('sec' => 0, 'usec' => 0),

                    'plugins' => array(
                        'Pong',
                        'NickServ',
                        'AutoJoin',
                        'Statusnet',
                    ),

                    'plugins.autoload' => true,

                    'ui.enabled' => true,

                    'nickserv.password' => $this->plugin->nickservpassword,
                    'nickserv.identify_message' => $this->plugin->nickservidentifyregexp,

                    'autojoin.channels' => $this->plugin->channels,

                    'statusnet.messagecallback' => array($this, 'handle_irc_message'),
                    'statusnet.regcallback' => array($this, 'handle_reg_response'),
                    'statusnet.unregregexp' => $this->plugin->unregregexp,
                    'statusnet.regregexp' => $this->plugin->regregexp
                )
            );

            $this->conn->setConfig($config);
            $this->conn->connect();
        }
        return $this->conn;
    }

    /**
    * Called via a callback when a message is received
    *
    * Passes it back to the queuing system
    *
    * @param array $data Data
    * @return boolean
    */
    public function handle_irc_message($data) {
        $this->plugin->enqueue_incoming_raw($data);
        return true;
    }

    /**
    * Called via a callback when NickServ responds to
    * the bots query asking if a nick is registered
    *
    * @param array $data Data
    * @return void
    */
    public function handle_reg_response($data) {
        // Retrieve data
        $screenname = $data['screenname'];
        $nickdata = $this->regchecks[$screenname];
        $usernick = $nickdata['user']->nickname;

        if (isset($this->regchecksLookup[$usernick])) {
            if ($data['registered']) {
                // Send message
                $this->plugin->send_confirmation_code($screenname, $nickdata['code'], $nickdata['user'], true);
            } else {
                $this->plugin->send_message($screenname, _m('Your nickname is not registered so IRC connectivity cannot be enabled'));

                $confirm = new Confirm_address();

                $confirm->user_id      = $user->id;
                $confirm->address_type = $this->plugin->transport;

                if ($confirm->find(true)) {
                    $result = $confirm->delete();

                    if (!$result) {
                        common_log_db_error($confirm, 'DELETE', __FILE__);
                        // TRANS: Server error thrown on database error canceling IM address confirmation.
                        $this->serverError(_('Couldn\'t delete confirmation.'));
                        return;
                    }
                }
            }

            // Unset lookup value
            unset($this->regchecksLookup[$usernick]);

            // Unset data
            unset($this->regchecks[$screename]);
        }
    }

    /**
     * Send a message using the daemon
     *
     * @param $data Message
     * @return boolean true on success
     */
    public function send_raw_message($data) {
        $this->connect();
        if (!$this->conn) {
            return false;
        }

        if ($data['type'] != 'message') {
            // Nick checking
            $nickdata = $data['nickdata'];
            $usernick = $nickdata['user']->nickname;
            $screenname = $nickdata['screenname'];

            // Cancel any existing checks for this user
            if (isset($this->regchecksLookup[$usernick])) {
                unset($this->regchecks[$this->regchecksLookup[$usernick]]);
            }

            $this->regchecks[$screenname] = $nickdata;
            $this->regchecksLookup[$usernick] = $screenname;
        }

        $args = $data['data']['args'];
        $lines = explode("\n", $args[1]);
        try {
            foreach ($lines as $line) {
                $this->conn->send($data['data']['command'], array($args[0], $line));
            }
        } catch (Phergie_Driver_Exception $e) {
            $this->conn->reconnect();
            return false;
        }

        return true;
    }
}
