<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Send and receive notices using the XMPP network
 *
 * PHP version 5
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
 *
 * @category  IM
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Plugin for XMPP
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class XmppPlugin extends ImPlugin
{
    public $server = null;
    public $port = 5222;
    public $user =  'update';
    public $resource = null;
    public $encryption = true;
    public $password = null;
    public $host = null;  // only set if != server
    public $debug = false; // print extra debug info

    public $transport = 'xmpp';

    protected $fake_xmpp;

    function getDisplayName(){
        return _m('XMPP/Jabber/GTalk');
    }

    function normalize($screenname)
    {
        if (preg_match("/(?:([^\@]+)\@)?([^\/]+)(?:\/(.*))?$/", $screenname, $matches)) {
            $node   = $matches[1];
            $server = $matches[2];
            return strtolower($node.'@'.$server);
        } else {
            return null;
        }
    }

    function daemon_screenname()
    {
        $ret = $this->user . '@' . $this->server;
        if($this->resource)
        {
            return $ret . '/' . $this->resource;
        }else{
            return $ret;
        }
    }

    function validate($screenname)
    {
        // Cheap but effective
        return Validate::email($screenname);
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Sharing_XMPP':
        case 'Fake_XMPP':
            include_once $dir . '/'.$cls.'.php';
            return false;
        case 'XmppManager':
            include_once $dir . '/'.strtolower($cls).'.php';
            return false;
        default:
            return true;
        }
    }

    function onStartImDaemonIoManagers(&$classes)
    {
        parent::onStartImDaemonIoManagers(&$classes);
        $classes[] = new XmppManager($this); // handles pings/reconnects
        return true;
    }

    function microiduri($screenname)
    {
        return 'xmpp:' . $screenname;    
    }

    function send_message($screenname, $body)
    {
        $this->fake_xmpp->message($screenname, $body, 'chat');
        $this->enqueue_outgoing_raw($this->fake_xmpp->would_be_sent);
        return true;
    }

    function send_notice($screenname, $notice)
    {
        $msg   = $this->format_notice($notice);
        $entry = $this->format_entry($notice);
        
        $this->fake_xmpp->message($screenname, $msg, 'chat', null, $entry);
        $this->enqueue_outgoing_raw($this->fake_xmpp->would_be_sent);
        return true;
    }

    /**
     * extra information for XMPP messages, as defined by Twitter
     *
     * @param Profile $profile Profile of the sending user
     * @param Notice  $notice  Notice being sent
     *
     * @return string Extra information (Atom, HTML, addresses) in string format
     */

    function format_entry($notice)
    {
        $profile = $notice->getProfile();

        $entry = $notice->asAtomEntry(true, true);

        $xs = new XMLStringer();
        $xs->elementStart('html', array('xmlns' => 'http://jabber.org/protocol/xhtml-im'));
        $xs->elementStart('body', array('xmlns' => 'http://www.w3.org/1999/xhtml'));
        $xs->element('a', array('href' => $profile->profileurl),
                     $profile->nickname);
        $xs->text(": ");
        if (!empty($notice->rendered)) {
            $xs->raw($notice->rendered);
        } else {
            $xs->raw(common_render_content($notice->content, $notice));
        }
        $xs->text(" ");
        $xs->element('a', array(
            'href'=>common_local_url('conversation',
                array('id' => $notice->conversation)).'#notice-'.$notice->id
             ),sprintf(_('[%s]'),$notice->id));
        $xs->elementEnd('body');
        $xs->elementEnd('html');

        $html = $xs->getString();

        return $html . ' ' . $entry;
    }

    function receive_raw_message($pl)
    {
        $from = $this->normalize($pl['from']);

        if ($pl['type'] != 'chat') {
            common_log(LOG_WARNING, "Ignoring message of type ".$pl['type']." from $from.");
            return true;
        }

        if (mb_strlen($pl['body']) == 0) {
            common_log(LOG_WARNING, "Ignoring message with empty body from $from.");
            return true;
        }

        return $this->handle_incoming($from, $pl['body']);
    }

    function initialize(){
        if(!isset($this->server)){
            throw new Exception("must specify a server");
        }
        if(!isset($this->port)){
            throw new Exception("must specify a port");
        }
        if(!isset($this->user)){
            throw new Exception("must specify a user");
        }
        if(!isset($this->password)){
            throw new Exception("must specify a password");
        }

        $this->fake_xmpp = new Fake_XMPP($this->host ?
                                    $this->host :
                                    $this->server,
                                    $this->port,
                                    $this->user,
                                    $this->password,
                                    $this->resource,
                                    $this->server,
                                    $this->debug ?
                                    true : false,
                                    $this->debug ?
                                    XMPPHP_Log::LEVEL_VERBOSE :  null
                                    );
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'XMPP',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews, Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:XMPP',
                            'rawdescription' =>
                            _m('The XMPP plugin allows users to send and receive notices over the XMPP/Jabber network.'));
        return true;
    }
}

