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

define('DEFAULT_HUB', 'http://pubsubhubbub.appspot.com');

require_once INSTALLDIR.'/plugins/PubSubHubBub/publisher.php';

/**
 * Plugin to provide publisher side of PubSubHubBub (PuSH)
 * relationship.
 *
 * PuSH is a real-time or near-real-time protocol for Atom
 * and RSS feeds. More information here:
 *
 * http://code.google.com/p/pubsubhubbub/
 *
 * To enable, add the following line to your config.php:
 *
 * addPlugin('PubSubHubBub');
 *
 * This will use the Google default hub. If you'd like to use
 * another, try:
 *
 * addPlugin('PubSubHubBub',
 *           array('hub' => 'http://yourhub.example.net/'));
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class PubSubHubBubPlugin extends Plugin
{
    /**
     * URL of the hub to advertise and publish to.
     */

    public $hub = DEFAULT_HUB;

    /**
     * Default constructor.
     */

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Hooks the StartApiAtom event
     *
     * Adds the necessary bits to advertise PubSubHubBub
     * for the Atom feed.
     *
     * @param Action $action The API action being shown.
     *
     * @return boolean hook value
     */

    function onStartApiAtom($action)
    {
        $action->element('link', array('rel' => 'hub', 'href' => $this->hub), null);

        return true;
    }

    /**
     * Hooks the StartApiRss event
     *
     * Adds the necessary bits to advertise PubSubHubBub
     * for the RSS 2.0 feeds.
     *
     * @param Action $action The API action being shown.
     *
     * @return boolean hook value
     */

    function onStartApiRss($action)
    {
        $action->element('atom:link', array('rel' => 'hub',
                                            'href' => $this->hub),
                         null);
        return true;
    }

    /**
     * Hook for a queued notice.
     *
     * When a notice has been queued, will ping the
     * PuSH hub for each Atom and RSS feed in which
     * the notice appears.
     *
     * @param Notice $notice The notice that's been queued
     *
     * @return boolean hook value
     */

    function onHandleQueuedNotice($notice)
    {
        $publisher = new Publisher($this->hub);

        $feeds = array();

        //public timeline feeds
        $feeds[] = common_local_url('ApiTimelinePublic', array('format' => 'rss'));
        $feeds[] = common_local_url('ApiTimelinePublic', array('format' => 'atom'));

        //author's own feeds
        $user = User::staticGet('id', $notice->profile_id);

        $feeds[] = common_local_url('ApiTimelineUser',
                                    array('id' => $user->nickname,
                                          'format' => 'rss'));
        $feeds[] = common_local_url('ApiTimelineUser',
                                    array('id' => $user->nickname,
                                          'format' => 'atom'));

        //tag feeds
        $tag = new Notice_tag();

        $tag->notice_id = $notice->id;
        if ($tag->find()) {
            while ($tag->fetch()) {
                $feeds[] = common_local_url('ApiTimelineTag',
                                            array('tag' => $tag->tag,
                                                  'format' => 'rss'));
                $feeds[] = common_local_url('ApiTimelineTag',
                                            array('tag' => $tag->tag,
                                                  'format' => 'atom'));
            }
        }

        //group feeds
        $group_inbox = new Group_inbox();

        $group_inbox->notice_id = $notice->id;
        if ($group_inbox->find()) {
            while ($group_inbox->fetch()) {
                $group = User_group::staticGet('id', $group_inbox->group_id);

                $feeds[] = common_local_url('ApiTimelineGroup',
                                            array('id' => $group->nickname,
                                                  'format' => 'rss'));
                $feeds[] = common_local_url('ApiTimelineGroup',
                                            array('id' => $group->nickname,
                                                  'format' => 'atom'));
            }
        }

        //feed of each user that subscribes to the notice's author

        $ni = $notice->whoGets();

        foreach (array_keys($ni) as $user_id) {
            $user = User::staticGet('id', $user_id);
            if (empty($user)) {
                continue;
            }
            $feeds[] = common_local_url('ApiTimelineFriends',
                                        array('id' => $user->nickname,
                                              'format' => 'rss'));
            $feeds[] = common_local_url('ApiTimelineFriends',
                                        array('id' => $user->nickname,
                                              'format' => 'atom'));
        }

        $replies = $notice->getReplies();

        //feed of user replied to
        foreach ($replies as $recipient) {
                $user = User::staticGet('id', $recipient);
            if (!empty($user)) {
                $feeds[] = common_local_url('ApiTimelineMentions',
                                            array('id' => $user->nickname,
                                                  'format' => 'rss'));
                $feeds[] = common_local_url('ApiTimelineMentions',
                                            array('id' => $user->nickname,
                                                  'format' => 'atom'));
            }
        }
        $feeds = array_unique($feeds);

        ob_start();
        $ok = $publisher->publish_update($feeds);
        $push_last_response = ob_get_clean();

        if (!$ok) {
            common_log(LOG_WARNING,
                       'Failure publishing ' . count($feeds) . ' feeds to hub at '.
                       $this->hub.': '.$push_last_response);
        } else {
            common_log(LOG_INFO,
                       'Published ' . count($feeds) . ' feeds to hub at '.
                       $this->hub.': '.$push_last_response);
        }

        return true;
    }

    /**
     * Provide version information
     *
     * Adds this plugin's version data to the global
     * version array, for e.g. displaying on the version page.
     *
     * @param array &$versions array of array of versions
     *
     * @return boolean hook value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'PubSubHubBub',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' =>
                            'http://status.net/wiki/Plugin:PubSubHubBub',
                            'rawdescription' =>
                            _m('The PubSubHubBub plugin pushes RSS/Atom updates '.
                               'to a <a href = "'.
                               'http://pubsubhubbub.googlecode.com/'.
                               '">PubSubHubBub</a> hub.'));

        return true;
    }
}
