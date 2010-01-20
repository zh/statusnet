<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to push RSS/Atom updates to a PubSubHubBub hub
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('DEFAULT_HUB','http://pubsubhubbub.appspot.com');

require_once(INSTALLDIR.'/plugins/PubSubHubBub/publisher.php');

class PubSubHubBubPlugin extends Plugin
{
    public $hub = DEFAULT_HUB;

    function __construct()
    {
        parent::__construct();
    }

    function onStartApiAtom($action){
        $action->element('link',array('rel'=>'hub','href'=>$this->hub),null);
    }

    function onStartApiRss($action){
        $action->element('atom:link',array('rel'=>'hub','href'=>$this->hub),null);
    }

    function onHandleQueuedNotice($notice){
        $publisher = new Publisher($this->hub);

        $feeds = array();

        //public timeline feeds
        $feeds[]=common_local_url('ApiTimelinePublic',array('format' => 'rss'));
        $feeds[]=common_local_url('ApiTimelinePublic',array('format' => 'atom'));

        //author's own feeds
        $user = User::staticGet('id',$notice->profile_id);
        $feeds[]=common_local_url('ApiTimelineUser',array('id' => $user->nickname, 'format'=>'rss'));
        $feeds[]=common_local_url('ApiTimelineUser',array('id' => $user->nickname, 'format'=>'atom'));

        //tag feeds
        $tag = new Notice_tag();
        $tag->notice_id = $notice->id;
        if ($tag->find()) {
            while ($tag->fetch()) {
                $feeds[]=common_local_url('ApiTimelineTag',array('tag'=>$tag->tag, 'format'=>'rss'));
                $feeds[]=common_local_url('ApiTimelineTag',array('tag'=>$tag->tag, 'format'=>'atom'));
            }
        }

        //group feeds
        $group_inbox = new Group_inbox();
        $group_inbox->notice_id = $notice->id;
        if ($group_inbox->find()) {
            while ($group_inbox->fetch()) {
                $group = User_group::staticGet('id',$group_inbox->group_id);
                $feeds[]=common_local_url('ApiTimelineGroup',array('id' => $group->nickname,'format'=>'rss'));
                $feeds[]=common_local_url('ApiTimelineGroup',array('id' => $group->nickname,'format'=>'atom'));
            }
        }

        //feed of each user that subscribes to the notice's author

        $ni = $notice->whoGets();

        foreach (array_keys($ni) as $user_id) {
            $user = User::staticGet('id', $user_id);
            if (empty($user)) {
                continue;
            }
            $feeds[]=common_local_url('ApiTimelineFriends', array('id' => $user->nickname, 'format'=>'rss'));
            $feeds[]=common_local_url('ApiTimelineFriends', array('id' => $user->nickname, 'format'=>'atom'));
        }

        $replies = $notice->getReplies();

        //feed of user replied to
        foreach ($replies as $recipient) {
                $user = User::staticGet('id',$recipient);
            if (!empty($user)) {
                $feeds[]=common_local_url('ApiTimelineMentions',array('id' => $user->nickname,'format'=>'rss'));
                $feeds[]=common_local_url('ApiTimelineMentions',array('id' => $user->nickname,'format'=>'atom'));
            }
        }

        foreach(array_unique($feeds) as $feed){
            if(! $publisher->publish_update($feed)){
                common_log_line(LOG_WARNING,$feed.' was not published to hub at '.$this->hub.':'.$publisher->last_response());
            }
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'PubSubHubBub',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:PubSubHubBub',
                            'rawdescription' =>
                            _m('The PubSubHubBub plugin pushes RSS/Atom updates to a <a href="http://pubsubhubbub.googlecode.com/">PubSubHubBub</a> hub.'));

        return true;
    }
}
