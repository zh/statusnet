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
        common_log(LOG_DEBUG, "Servicing the IRC queue.");
        $this->stats('irc_process');
        $this->conn->receive();
    }

    function connect() {
        if (!$this->conn) {
            $this->conn = new Phergie_Bot;

            $password = isset($this->plugin->password) ? $this->plugin->password : NULL;
            $transport = isset($this->plugin->transport) ? $this->plugin->transport : 'tcp';
            $encoding = isset($this->plugin->encoding) ? $this->plugin->encoding : 'ISO-8859-1';

            $config = new Phergie_Extended_Config;
            $config->readArray(
                array(
                    // One array per connection, pretty self-explanatory
                    'connections' => array(
                        // Ex: All connection info for the Freenode network
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

                    'processor' => 'async',
                    'processor.options' => array('usec' => 200000),
                    // Time zone. See: http://www.php.net/manual/en/timezones.php
                    'timezone' => 'UTC',

    // Whitelist of plugins to load
    'plugins' => array(
        // To enable a plugin, simply add a string to this array containing
        // the short name of the plugin as shown below.

        // 'ShortPluginName',

        // Below is an example of enabling the AutoJoin plugin, for which
        // the corresponding PEAR package is Phergie_Plugin_AutoJoin. This
        // plugin allows you to set a list of channels in this configuration
        // file that the bot will automatically join when it connects to a
        // server. If you'd like to enable this plugin, simply install it,
        // uncomment the line below, and set a value for the setting
        // autojoin.channels (examples for which are located further down in
        // this file).

        // 'AutoJoin',

        // A few other recommended plugins:

        // Servers randomly send PING events to clients to ensure that
        // they're still connected and will eventually terminate the

        // connection if a PONG response is not received. The Pong plugin
        // handles sending these responses.

        // 'Pong',

        // It's sometimes difficult to distinguish between a lack of
        // activity on a server and the client not receiving data even
        // though a connection remains open. The Ping plugin performs a self
        // CTCP PING sporadically to ensure that its connection is still
        // functioning and, if not, terminates the bot.

        // 'Ping',

        // Sometimes it's desirable to have the bot disconnect gracefully
        // when issued a command to do so via a PRIVMSG event. The Quit
        // plugin implements this using the Command plugin to intercept the
        // command.

        // 'Quit',
    ),

    // If set to true, this allows any plugin dependencies for plugins
    // listed in the 'plugins' option to be loaded even if they are not
    // explicitly included in that list
    'plugins.autoload' => true,

    // Enables shell output describing bot events via Phergie_Ui_Console
    'ui.enabled' => true,

    // Examples of supported values for autojoins.channel:
    // 'autojoin.channels' => '#channel1,#channel2',
    // 'autojoin.channels' => array('#channel1', '#channel2'),
    // 'autojoin.channels' => array(
    //                            'host1' => '#channel1,#channel2',
    //                            'host2' => array('#channel3', '#channel4')
    //                        ),

    // Examples of setting values for Ping plugin settings

    // This is the amount of time in seconds that the Ping plugin will wait
    // to receive an event from the server before it initiates a self-ping

    // 'ping.event' => 300, // 5 minutes

    // This is the amount of time in seconds that the Ping plugin will wait
    // following a self-ping attempt before it assumes that a response will
    // never be received and terminates the connection

    // 'ping.ping' => 10, // 10 seconds

));
            $this->conn=new Aim($this->plugin->user,$this->plugin->password,4);
            $this->conn->registerHandler("IMIn",array($this,"handle_aim_message"));
            $this->conn->myServer="toc.oscar.aol.com";
            $this->conn->signon();
            $this->conn->setProfile(_m('Send me a message to post a notice'), false);
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
