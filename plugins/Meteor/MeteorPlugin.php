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

/**
 * Plugin to do realtime updates using Comet
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class MeteorPlugin extends Plugin
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

    function onEndShowScripts($action)
    {
        $timeline = null;

        switch ($action->trimmed('action')) {
         case 'public':
            $timeline = 'timelines-public';
            break;
         case 'tag':
            $tag = $action->trimmed('tag');
            if (!empty($tag)) {
                $timeline = 'timelines-tag-'.$tag;
            } else {
                return true;
            }
            break;
         default:
            return true;
        }

        $action->element('script', array('type' => 'text/javascript',
                                         'src' => 'http://'.$this->webserver.(($this->webport == 80) ? '':':'.$this->webport).'/meteor.js'),
                         ' ');

        foreach (array('meteorupdater.js', 'json2.js') as $script) {
            $action->element('script', array('type' => 'text/javascript',
                                             'src' => common_path('plugins/Meteor/'.$script)),
                         ' ');
        }

        $user = common_current_user();

        if (!empty($user->id)) {
            $user_id = $user->id;
        } else {
            $user_id = 0;
        }

        $replyurl = common_local_url('newnotice');
        $favorurl = common_local_url('favor');
        // FIXME: need to find a better way to pass this pattern in
        $deleteurl = common_local_url('deletenotice',
                                      array('notice' => '0000000000'));

        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw("$(document).ready(function() { MeteorUpdater.init(\"$this->webserver\", $this->webport, \"{$this->channelbase}{$timeline}\", $user_id, \"$replyurl\", \"$favorurl\", \"$deleteurl\"); });");
        $action->elementEnd('script');

        return true;
    }

    function onEndNoticeSave($notice)
    {
        $timelines = array();

        // XXX: Add other timelines; this is just for the public one

        if ($notice->is_local ||
            ($notice->is_local == 0 && !common_config('public', 'localonly'))) {
            $timelines[] = 'timelines-public';
        }

        $tags = $this->getNoticeTags($notice);

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $timelines[] = 'timelines-tag-' . $tag;
            }
        }

        if (count($timelines) > 0) {

            $json = json_encode($this->noticeAsJson($notice));

            $this->_connect();

            foreach ($timelines as $timeline) {
                $this->_addMessage($timeline, $json);
            }

            $this->_disconnect();
        }

        return true;
    }

    protected function _connect()
    {
        $controlserver = (empty($this->controlserver)) ? $this->webserver : $this->controlserver;
        // May throw an exception.
        $this->_socket = stream_socket_client("tcp://{$controlserver}:{$this->controlport}");
        if (!$this->_socket) {
            throw new Exception("Couldn't connect to {$controlserver} on {$this->controlport}");
        }
    }

    protected function _addMessage($channel, $message)
    {
        $message = addslashes($message);
        $cmd = "ADDMESSAGE {$this->channelbase}{$channel} $message\n";
        $cnt = fwrite($this->_socket, $cmd);
        $result = fgets($this->_socket);
        if (preg_match('/^ERR (.*)$/', $result, $matches)) {
            throw new Exception('Error adding meteor message "'.$matches[1].'"');
        }
        // TODO: parse and deal with result
    }

    protected function _disconnect()
    {
        $cnt = fwrite($this->_socket, "QUIT\n");
        @fclose($this->_socket);
    }

    function noticeAsJson($notice)
    {
        // FIXME: this code should be abstracted to a neutral third
        // party, like Notice::asJson(). I'm not sure of the ethics
        // of refactoring from within a plugin, so I'm just abusing
        // the TwitterApiAction method. Don't do this unless you're me!

        require_once(INSTALLDIR.'/lib/twitterapi.php');

        $act = new TwitterApiAction('/dev/null');

        $arr = $act->twitter_status_array($notice, true);
        $arr['url'] = $notice->bestUrl();
        $arr['html'] = htmlspecialchars($notice->rendered);
        $arr['source'] = htmlspecialchars($arr['source']);

        if (!empty($notice->reply_to)) {
            $reply_to = Notice::staticGet('id', $notice->reply_to);
            if (!empty($reply_to)) {
                $arr['in_reply_to_status_url'] = $reply_to->bestUrl();
            }
            $reply_to = null;
        }

        $profile = $notice->getProfile();
        $arr['user']['profile_url'] = $profile->profileurl;

        return $arr;
    }

    function getNoticeTags($notice)
    {
        $tags = null;

        $nt = new Notice_tag();
        $nt->notice_id = $notice->id;

        if ($nt->find()) {
            $tags = array();
            while ($nt->fetch()) {
                $tags[] = $nt->tag;
            }
        }

        $nt->free();
        $nt = null;

        return $tags;
    }

    // Push this up to Plugin

    function log($level, $msg)
    {
        common_log($level, get_class($this) . ': '.$msg);
    }
}
