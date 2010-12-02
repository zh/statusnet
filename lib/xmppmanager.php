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
    protected $conn = null;

    static protected $singletons = array();
    
    const PING_INTERVAL = 120;

    /**
     * Fetch the singleton XmppManager for the current site.
     * @return mixed XmppManager, or false if unneeded
     */
    public static function get()
    {
        if (common_config('xmpp', 'enabled')) {
            $site = StatusNet::currentSite();
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
        $this->site = StatusNet::currentSite();
        $this->resource = common_config('xmpp', 'resource') . 'daemon';
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
        $this->conn = jabber_connect($this->resource);

        if (empty($this->conn)) {
            common_log(LOG_ERR, "Couldn't connect to server.");
            return false;
        }

        $this->log(LOG_DEBUG, "Initializing stanza handlers.");

        $this->conn->addEventHandler('message', 'handle_message', $this);
        $this->conn->addEventHandler('presence', 'handle_presence', $this);
        $this->conn->addEventHandler('reconnect', 'handle_reconnect', $this);

        $this->conn->setReconnectTimeout(600);
        // @todo Needs i18n?
        jabber_send_presence("Send me a message to post a notice", 'available', null, 'available', 100);

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
        if ($this->conn) {
            return array($this->conn->getSocket());
        } else {
            return array();
        }
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
     * For queue handlers to pass us a message to push out,
     * if we're active.
     *
     * @fixme should this be blocking etc?
     *
     * @param string $msg XML stanza to send
     * @return boolean success
     */
    public function send($msg)
    {
        if ($this->conn && !$this->conn->isDisconnected()) {
            $bytes = $this->conn->send($msg);
            if ($bytes > 0) {
                $this->conn->processTime(0);
                return true;
            } else {
                common_log(LOG_ERR, __METHOD__ . ' failed: 0 bytes sent');
                return false;
            }
        } else {
            // Can't send right now...
            common_log(LOG_ERR, __METHOD__ . ' failed: XMPP server connection currently down');
            return false;
        }
    }

    /**
     * Send a keepalive ping to the XMPP server.
     */
    protected function sendPing()
    {
        $jid = jabber_daemon_address().'/'.$this->resource;
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
        $this->conn->presence(null, 'available', null, 'available', 100);
    }


    function get_user($from)
    {
        $user = User::staticGet('jabber', jabber_normalize_jid($from));
        return $user;
    }

    /**
     * XMPP callback for handling message input...
     * @param array $pl XMPP payload
     */
    function handle_message(&$pl)
    {
        $from = jabber_normalize_jid($pl['from']);

        if ($pl['type'] != 'chat') {
            $this->log(LOG_WARNING, "Ignoring message of type ".$pl['type']." from $from: " . $pl['xml']->toString());
            return;
        }

        if (mb_strlen($pl['body']) == 0) {
            $this->log(LOG_WARNING, "Ignoring message with empty body from $from: "  . $pl['xml']->toString());
            return;
        }

        // Forwarded from another daemon for us to handle; this shouldn't
        // happen any more but we might get some legacy items.
        if ($this->is_self($from)) {
            $this->log(LOG_INFO, "Got forwarded notice from self ($from).");
            $from = $this->get_ofrom($pl);
            $this->log(LOG_INFO, "Originally sent by $from.");
            if (is_null($from) || $this->is_self($from)) {
                $this->log(LOG_INFO, "Ignoring notice originally sent by $from.");
                return;
            }
        }

        $user = $this->get_user($from);

        // For common_current_user to work
        global $_cur;
        $_cur = $user;

        if (!$user) {
            // TRANS: %s is the URL to the StatusNet site's Instant Messaging settings.
            $this->from_site($from, sprintf(_('Unknown user. Go to %s ' .
                             'to add your address to your account'),common_local_url('imsettings')));
            $this->log(LOG_WARNING, 'Message from unknown user ' . $from);
            return;
        }
        if ($this->handle_command($user, $pl['body'])) {
            $this->log(LOG_INFO, "Command message by $from handled.");
            return;
        } else if ($this->is_autoreply($pl['body'])) {
            $this->log(LOG_INFO, 'Ignoring auto reply from ' . $from);
            return;
        } else if ($this->is_otr($pl['body'])) {
            $this->log(LOG_INFO, 'Ignoring OTR from ' . $from);
            return;
        } else {

            $this->log(LOG_INFO, 'Posting a notice from ' . $user->nickname);

            $this->add_notice($user, $pl);
        }

        $user->free();
        unset($user);
        unset($_cur);

        unset($pl['xml']);
        $pl['xml'] = null;

        $pl = null;
        unset($pl);
    }

    function is_self($from)
    {
        return preg_match('/^'.strtolower(jabber_daemon_address()).'/', strtolower($from));
    }

    function get_ofrom($pl)
    {
        $xml = $pl['xml'];
        $addresses = $xml->sub('addresses');
        if (!$addresses) {
            $this->log(LOG_WARNING, 'Forwarded message without addresses');
            return null;
        }
        $address = $addresses->sub('address');
        if (!$address) {
            $this->log(LOG_WARNING, 'Forwarded message without address');
            return null;
        }
        if (!array_key_exists('type', $address->attrs)) {
            $this->log(LOG_WARNING, 'No type for forwarded message');
            return null;
        }
        $type = $address->attrs['type'];
        if ($type != 'ofrom') {
            $this->log(LOG_WARNING, 'Type of forwarded message is not ofrom');
            return null;
        }
        if (!array_key_exists('jid', $address->attrs)) {
            $this->log(LOG_WARNING, 'No jid for forwarded message');
            return null;
        }
        $jid = $address->attrs['jid'];
        if (!$jid) {
            $this->log(LOG_WARNING, 'Could not get jid from address');
            return null;
        }
        $this->log(LOG_DEBUG, 'Got message forwarded from jid ' . $jid);
        return $jid;
    }

    function is_autoreply($txt)
    {
        if (preg_match('/[\[\(]?[Aa]uto[-\s]?[Rr]e(ply|sponse)[\]\)]/', $txt)) {
            return true;
        } else if (preg_match('/^System: Message wasn\'t delivered. Offline storage size was exceeded.$/', $txt)) {
            return true;
        } else {
            return false;
        }
    }

    function is_otr($txt)
    {
        if (preg_match('/^\?OTR/', $txt)) {
            return true;
        } else {
            return false;
        }
    }

    function from_site($address, $msg)
    {
        $text = '['.common_config('site', 'name') . '] ' . $msg;
        jabber_send_message($address, $text);
    }

    function handle_command($user, $body)
    {
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $body);
        if ($cmd) {
            $chan = new XMPPChannel($this->conn);
            $cmd->execute($chan);
            return true;
        } else {
            return false;
        }
    }

    function add_notice(&$user, &$pl)
    {
        $body = trim($pl['body']);
        $content_shortened = $user->shortenLinks($body);
        if (Notice::contentTooLong($content_shortened)) {
          $from = jabber_normalize_jid($pl['from']);
          // TRANS: Response to XMPP source when it sent too long a message.
          // TRANS: %1$d the maximum number of allowed characters (used for plural), %2$d is the sent number.
          $this->from_site($from, sprintf(_m('Message too long. Maximum is %1$d character, you sent %2$d.',
                                             'Message too long. Maximum is %1$d characters, you sent %2$d.',
                                             Notice::maxContent()),
                                          Notice::maxContent(),
                                          mb_strlen($content_shortened)));
          return;
        }

        try {
            $notice = Notice::saveNew($user->id, $content_shortened, 'xmpp');
        } catch (Exception $e) {
            $this->log(LOG_ERR, $e->getMessage());
            $this->from_site($user->jabber, $e->getMessage());
            return;
        }

        common_broadcast_notice($notice);
        $this->log(LOG_INFO,
                   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
        $notice->free();
        unset($notice);
    }

    function handle_presence(&$pl)
    {
        $from = jabber_normalize_jid($pl['from']);
        switch ($pl['type']) {
         case 'subscribe':
            # We let anyone subscribe
            $this->subscribed($from);
            $this->log(LOG_INFO,
                       'Accepted subscription from ' . $from);
            break;
         case 'subscribed':
         case 'unsubscribed':
         case 'unsubscribe':
            $this->log(LOG_INFO,
                       'Ignoring  "' . $pl['type'] . '" from ' . $from);
            break;
         default:
            if (!$pl['type']) {
                $user = User::staticGet('jabber', $from);
                if (!$user) {
                    $this->log(LOG_WARNING, 'Presence from unknown user ' . $from);
                    return;
                }
                if ($user->updatefrompresence) {
                    $this->log(LOG_INFO, 'Updating ' . $user->nickname .
                               ' status from presence.');
                    $this->add_notice($user, $pl);
                }
                $user->free();
                unset($user);
            }
            break;
        }
        unset($pl['xml']);
        $pl['xml'] = null;

        $pl = null;
        unset($pl);
    }

    function log($level, $msg)
    {
        $text = 'XMPPDaemon('.$this->resource.'): '.$msg;
        common_log($level, $text);
    }

    function subscribed($to)
    {
        jabber_special_presence('subscribed', $to);
    }

    /**
     * Make sure we're on the right site configuration
     */
    protected function switchSite()
    {
        if ($this->site != StatusNet::currentSite()) {
            common_log(LOG_DEBUG, __METHOD__ . ": switching to site $this->site");
            $this->stats('switch');
            StatusNet::switchSite($this->site);
        }
    }
}
