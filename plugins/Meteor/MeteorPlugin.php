<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to do "real time" updates using Comet/Bayeux
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/Realtime/RealtimePlugin.php';

/**
 * Plugin to do realtime updates using Meteor
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class MeteorPlugin extends RealtimePlugin
{
    public $webserver     = null;
    public $webport       = null;
    public $controlport   = null;
    public $controlserver = null;
    public $channelbase   = null;
    protected $_socket    = null;

    function __construct($webserver=null, $webport=4670, $controlport=4671, $controlserver=null, $channelbase='')
    {
        global $config;

        $this->webserver     = (empty($webserver)) ? $config['site']['server'] : $webserver;
        $this->webport       = $webport;
        $this->controlport   = $controlport;
        $this->controlserver = (empty($controlserver)) ? $webserver : $controlserver;
        $this->channelbase   = $channelbase;

        parent::__construct();
    }

    function _getScripts()
    {
        $scripts = parent::_getScripts();
        $scripts[] = 'http://'.$this->webserver.(($this->webport == 80) ? '':':'.$this->webport).'/meteor.js';
        $scripts[] = common_path('plugins/Meteor/meteorupdater.js');
        return $scripts;
    }

    function _updateInitialize($timeline, $user_id)
    {
        $script = parent::_updateInitialize($timeline, $user_id);
        return $script." MeteorUpdater.init(\"$this->webserver\", $this->webport, \"{$timeline}\");";
    }

    function _connect()
    {
        $controlserver = (empty($this->controlserver)) ? $this->webserver : $this->controlserver;
        // May throw an exception.
        $this->_socket = stream_socket_client("tcp://{$controlserver}:{$this->controlport}");
        if (!$this->_socket) {
            throw new Exception("Couldn't connect to {$controlserver} on {$this->controlport}");
        }
    }

    function _publish($channel, $message)
    {
        $message = json_encode($message);
        $message = addslashes($message);
        $cmd = "ADDMESSAGE $channel $message\n";
        $cnt = fwrite($this->_socket, $cmd);
        $result = fgets($this->_socket);
        if (preg_match('/^ERR (.*)$/', $result, $matches)) {
            throw new Exception('Error adding meteor message "'.$matches[1].'"');
        }
        // TODO: parse and deal with result
    }

    function _disconnect()
    {
        $cnt = fwrite($this->_socket, "QUIT\n");
        @fclose($this->_socket);
    }

    // Meteord flips out with default '/' separator

    function _pathToChannel($path)
    {
        if (!empty($this->channelbase)) {
            array_unshift($path, $this->channelbase);
        }
        return implode('-', $path);
    }
}
