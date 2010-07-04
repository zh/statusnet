<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Send and receive notices using the AIM network
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
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}
// We bundle the Phergie library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/phergie');
require 'Phergie/Autoload.php';
Phergie_Autoload::registerAutoloader();

/**
 * Plugin for IRC
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class IrcPlugin extends ImPlugin {
    public $user =  null;
    public $password = null;
    public $publicFeed = array();

    public $transport = 'irc';

    function getDisplayName() {
        return _m('IRC');
    }

    function normalize($screenname) {
		$screenname = str_replace(" ","", $screenname);
        return strtolower($screenname);
    }

    function daemon_screenname() {
        return $this->user;
    }

    function validate($screenname) {
        if (preg_match('/^[a-z]\w{2,15}$/i', $screenname)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls) {
        $dir = dirname(__FILE__);

        switch ($cls) {
            case 'IrcManager':
                include_once $dir . '/'.strtolower($cls).'.php';
                return false;
            case 'Fake_Irc':
                include_once $dir . '/'. $cls .'.php';
                return false;
            default:
                return true;
        }
    }

    function onStartImDaemonIoManagers(&$classes) {
        parent::onStartImDaemonIoManagers(&$classes);
        $classes[] = new IrcManager($this); // handles sending/receiving
        return true;
    }

    function microiduri($screenname) {
        return 'irc:' . $screenname;
    }

    function send_message($screenname, $body) {
        $this->fake_irc->sendIm($screenname, $body);
        $this->enqueue_outgoing_raw($this->fake_irc->would_be_sent);
        return true;
    }

    /**
     * Accept a queued input message.
     *
     * @return true if processing completed, false if message should be reprocessed
     */
    function receive_raw_message($message) {
        $info=Aim::getMessageInfo($message);
        $from = $info['from'];
        $user = $this->get_user($from);
        $notice_text = $info['message'];

        $this->handle_incoming($from, $notice_text);

        return true;
    }

    function initialize() {
        if (!isset($this->user)) {
            throw new Exception("must specify a user");
        }
        if (!isset($this->password)) {
            throw new Exception("must specify a password");
        }

        $this->fake_irc = new Fake_Irc($this->user, $this->password, 4);
        return true;
    }

    function onPluginVersion(&$versions) {
        $versions[] = array('name' => 'IRC',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Luke Fitzgerald',
                            'homepage' => 'http://status.net/wiki/Plugin:IRC',
                            'rawdescription' =>
                            _m('The IRC plugin allows users to send and receive notices over an IRC network.'));
        return true;
    }
}
