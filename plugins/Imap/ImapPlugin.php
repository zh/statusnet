<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add a StatusNet Facebook application
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
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * IMAP plugin to allow StatusNet to grab incoming emails and handle them as new user posts
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ImapPlugin extends Plugin
{
    public $mailbox;
    public $user;
    public $password;
    public $poll_frequency = 60;
    public static $instances = array();
    public static $daemon_added = array();

    function initialize(){
        if(!isset($this->mailbox)){
            throw new Exception("must specify a mailbox");
        }
        if(!isset($this->user)){
            throw new Exception("must specify a user");
        }
        if(!isset($this->password)){
            throw new Exception("must specify a password");
        }
        if(!isset($this->poll_frequency)){
            throw new Exception("must specify a poll_frequency");
        }

        self::$instances[] = $this;
        return true;
    }

    function cleanup(){
        $index = array_search($this, self::$instances);
        unset(self::$instances[$index]);
        return true;
    }

    function onGetValidDaemons($daemons)
    {
        if(! self::$daemon_added){
            array_push($daemons, INSTALLDIR .
                       '/plugins/Imap/imapdaemon.php');
            self::$daemon_added = true;
        }
        return true;
    }
}
