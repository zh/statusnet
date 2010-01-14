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
 * XMPP background connection manager for XMPP-using queue handlers,
 * allowing them to send outgoing messages on the right connection.
 *
 * Input is handled during socket select loop, keepalive pings during idle.
 * Any incoming messages will be forwarded to the main XmppDaemon process,
 * which handles direct user interaction.
 *
 * In a multi-site queuedaemon.php run, one connection will be instantiated
 * for each site being handled by the current process that has XMPP enabled.
 */

class XmppManager extends IoManager
{
    protected $site = null;
    protected $pingid = 0;
    protected $lastping = null;

    static protected $singletons = array();
    
    const PING_INTERVAL = 120;

    /**
     * Fetch the singleton XmppManager for the current site.
     * @return mixed XmppManager, or false if unneeded
     */
    public static function get()
    {
        if (common_config('xmpp', 'enabled')) {
            $site = common_config('site', 'server');
            if (empty(self::$singletons[$site])) {
                self::$singletons[$site] = new XmppManager();
            }
            return self::$singletons[$site];
        } else {
            return false;
        }
    }

    /**
     * Tell the i/o master we need one instance for each supporting site
     * being handled in this process.
     */
    public static function multiSite()
    {
        return IoManager::INSTANCE_PER_SITE;
    }

    function __construct()
    {
        $this->site = common_config('site', 'server');
    }

    /**
     * Initialize connection to server.
     * @return boolean true on success
     */
    public function start($master)
    {
        parent::start($master);
        $this->switchSite();

        require_once INSTALLDIR . "/lib/jabber.php";

        # Low priority; we don't want to receive messages

        common_log(LOG_INFO, "INITIALIZE");
        $this->conn = jabber_connect($this->resource());

        if (empty($this->conn)) {
            common_log(LOG_ERR, "Couldn't connect to server.");
            return false;
        }

        $this->conn->addEventHandler('message', 'forward_message', $this);
        $this->conn->addEventHandler('reconnect', 'handle_reconnect', $this);
        $this->conn->setReconnectTimeout(600);
        jabber_send_presence("Send me a message to post a notice", 'available', null, 'available', -1);

        return !is_null($this->conn);
    }

    /**
     * Message pump is triggered on socket input, so we only need an idle()
     * call often enough to trigger our outgoing pings.
     */
    function timeout()
    {
        return self::PING_INTERVAL;
    }

    /**
     * Lists the XMPP connection socket to allow i/o master to wake
     * when input comes in here as well as from the queue source.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        return array($this->conn->getSocket());
    }

    /**
     * Process XMPP events that have come in over the wire.
     * Side effects: may switch site configuration
     * @fixme may kill process on XMPP error
     * @param resource $socket
     */
    public function handleInput($socket)
    {
        $this->switchSite();

        # Process the queue for as long as needed
        try {
            if ($this->conn) {
                assert($socket === $this->conn->getSocket());
                
                common_log(LOG_DEBUG, "Servicing the XMPP queue.");
                $this->stats('xmpp_process');
                $this->conn->processTime(0);
            }
        } catch (XMPPHP_Exception $e) {
            common_log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
            die($e->getMessage());
        }
    }

    /**
     * Idle processing for io manager's execution loop.
     * Send keepalive pings to server.
     *
     * Side effect: kills process on exception from XMPP library.
     *
     * @fixme non-dying error handling
     */
    public function idle($timeout=0)
    {
        if ($this->conn) {
            $now = time();
            if (empty($this->lastping) || $now - $this->lastping > self::PING_INTERVAL) {
                $this->switchSite();
                try {
                    $this->sendPing();
                    $this->lastping = $now;
                } catch (XMPPHP_Exception $e) {
                    common_log(LOG_ERR, "Got an XMPPHP_Exception: " . $e->getMessage());
                    die($e->getMessage());
                }
            }
        }
    }

    /**
     * Send a keepalive ping to the XMPP server.
     */
    protected function sendPing()
    {
        $jid = jabber_daemon_address().'/'.$this->resource();
        $server = common_config('xmpp', 'server');

        if (!isset($this->pingid)) {
            $this->pingid = 0;
        } else {
            $this->pingid++;
        }

        common_log(LOG_DEBUG, "Sending ping #{$this->pingid}");

        $this->conn->send("<iq from='{$jid}' to='{$server}' id='ping_{$this->pingid}' type='get'><ping xmlns='urn:xmpp:ping'/></iq>");
    }

    /**
     * Callback for Jabber reconnect event
     * @param $pl
     */
    function handle_reconnect(&$pl)
    {
        common_log(LOG_NOTICE, 'XMPP reconnected');

        $this->conn->processUntil('session_start');
        $this->conn->presence(null, 'available', null, 'available', -1);
    }

    /**
     * Callback for Jabber message event.
     *
     * This connection handles output; if we get a message straight to us,
     * forward it on to our XmppDaemon listener for processing.
     *
     * @param $pl
     */
    function forward_message(&$pl)
    {
        if ($pl['type'] != 'chat') {
            common_log(LOG_DEBUG, 'Ignoring message of type ' . $pl['type'] . ' from ' . $pl['from']);
            return;
        }
        $listener = $this->listener();
        if (strtolower($listener) == strtolower($pl['from'])) {
            common_log(LOG_WARNING, 'Ignoring loop message.');
            return;
        }
        common_log(LOG_INFO, 'Forwarding message from ' . $pl['from'] . ' to ' . $listener);
        $this->conn->message($this->listener(), $pl['body'], 'chat', null, $this->ofrom($pl['from']));
    }

    /**
     * Build an <addresses> block with an ofrom entry for forwarded messages
     *
     * @param string $from Jabber ID of original sender
     * @return string XML fragment
     */
    protected function ofrom($from)
    {
        $address = "<addresses xmlns='http://jabber.org/protocol/address'>\n";
        $address .= "<address type='ofrom' jid='$from' />\n";
        $address .= "</addresses>\n";
        return $address;
    }

    /**
     * Build the complete JID of the XmppDaemon process which
     * handles primary XMPP input for this site.
     *
     * @return string Jabber ID
     */
    protected function listener()
    {
        if (common_config('xmpp', 'listener')) {
            return common_config('xmpp', 'listener');
        } else {
            return jabber_daemon_address() . '/' . common_config('xmpp','resource') . 'daemon';
        }
    }

    protected function resource()
    {
        return 'queue' . posix_getpid(); // @fixme PIDs won't be host-unique
    }

    /**
     * Make sure we're on the right site configuration
     */
    protected function switchSite()
    {
        if ($this->site != common_config('site', 'server')) {
            common_log(LOG_DEBUG, __METHOD__ . ": switching to site $this->site");
            $this->stats('switch');
            StatusNet::init($this->site);
        }
    }
}
