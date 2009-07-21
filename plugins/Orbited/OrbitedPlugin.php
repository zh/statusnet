<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to do "real time" updates using Orbited + STOMP
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
 * Plugin to do realtime updates using Orbited + STOMP
 *
 * This plugin pushes data to a STOMP server which is then served to the
 * browser by the Orbited server.
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class OrbitedPlugin extends RealtimePlugin
{
    public $webserver   = null;
    public $webport     = null;
    public $channelbase = null;
    public $stompserver = null;
    public $username    = null;
    public $password    = null;

    protected $con      = null;

    function _getScripts()
    {
        $scripts = parent::_getScripts();
        $root = 'http://'.$this->webserver.(($this->webport == 80) ? '':':'.$this->webport);
        $scripts[] = $root.'/static/Orbited.js';
        $scripts[] = $root.'/static/protocols/stomp/stomp.js';
        $scripts[] = common_path('plugins/Orbited/orbitedupdater.js');
        return $scripts;
    }

    function _updateInitialize($timeline, $user_id)
    {
        $script = parent::_updateInitialize($timeline, $user_id);
        return $script." OrbitedUpdater.init(\"$this->stompserver\", $this->stompport, \"{$timeline}\");";
    }

    function _connect()
    {
        require_once(INSTALLDIR.'/extlibs/Stomp.php');

        $stompserver = (empty($this->stompserver)) ? "tcp://{$this->webserver}:61613/" : $this->stompserver;

        $this->con = new Stomp($stompserver);

        if ($this->con->connect($this->username, $this->password)) {
            $this->_log(LOG_INFO, "Connected.");
        } else {
            $this->_log(LOG_ERR, 'Failed to connect to queue server');
            throw new ServerException('Failed to connect to queue server');
        }
    }

    function _publish($channel, $message)
    {
        $result = $this->con->send($channel,
                                   json_encode($message));

        return $result;
        // TODO: parse and deal with result
    }

    function _disconnect()
    {
        $this->con->disconnect();
    }

    function _pathToChannel($path)
    {
        if (!empty($this->channelbase)) {
            array_unshift($path, $this->channelbase);
        }
        return '/' . implode('/', $path);
    }
}
