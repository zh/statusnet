<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do "real time" updates of timelines using Ajax
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
 * Superclass for plugin to do realtime updates
 *
 * Based on experience with the Comet and Meteor plugins,
 * this superclass extracts out some of the common functionality
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class RealtimePlugin extends Plugin
{
    protected $replyurl = null;
    protected $favorurl = null;
    protected $deleteurl = null;

    function onInitializePlugin()
    {
        $this->replyurl = common_local_url('newnotice');
        $this->favorurl = common_local_url('favor');
        // FIXME: need to find a better way to pass this pattern in
        $this->deleteurl = common_local_url('deletenotice',
                                            array('notice' => '0000000000'));
    }

    function onEndShowScripts($action)
    {
        $path = null;

        switch ($action->trimmed('action')) {
         case 'public':
            $path = array('public');
            break;
         case 'tag':
            $tag = $action->trimmed('tag');
            if (!empty($tag)) {
                $path = array('tag', $tag);
            } else {
                return true;
            }
            break;
         default:
            return true;
        }

        $timeline = $this->_pathToChannel($path);

        $scripts = $this->_getScripts();

        foreach ($scripts as $script) {
            $action->element('script', array('type' => 'text/javascript',
                                             'src' => $script),
                         ' ');
        }

        $user = common_current_user();

        if (!empty($user->id)) {
            $user_id = $user->id;
        } else {
            $user_id = 0;
        }

        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw("$(document).ready(function() { ");
        $action->raw($this->_updateInitialize($timeline, $user_id));
        $action->raw(" });");
        $action->elementEnd('script');

        return true;
    }

    function onEndNoticeSave($notice)
    {
        $paths = array();

        // XXX: Add other timelines; this is just for the public one

        if ($notice->is_local ||
            ($notice->is_local == 0 && !common_config('public', 'localonly'))) {
            $paths[] = array('public');
        }

        $tags = $this->getNoticeTags($notice);

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $paths[] = array('tag', $tag);
            }
        }

        if (count($paths) > 0) {

            $json = $this->noticeAsJson($notice);

            $this->_connect();

            foreach ($paths as $path) {
                $timeline = $this->_pathToChannel($path);
                $this->_publish($timeline, $json);
            }

            $this->_disconnect();
        }

        return true;
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

    function _getScripts()
    {
        return array(common_path('plugins/Realtime/realtimeupdate.js'),
                     common_path('plugins/Realtime/json2.js'));
    }

    function _updateInitialize($timeline, $user_id)
    {
        return "RealtimeUpdate.init($user_id, \"$this->replyurl\", \"$this->favorurl\", \"$this->deleteurl\"); ";
    }

    function _connect()
    {
    }

    function _publish($timeline, $json)
    {
    }

    function _disconnect()
    {
    }

    function _pathToChannel($path)
    {
        return '';
    }
}
