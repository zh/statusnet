<?php
/*
StatusNet Plugin: 0.9
Plugin Name: FeedSub
Plugin URI: http://status.net/wiki/Feed_subscription
Description: FeedSub allows subscribing to real-time updates from external feeds supporting PubHubSubbub protocol.
Version: 0.1
Author: Brion Vibber <brion@status.net>
Author URI: http://status.net/
*/

/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

/**
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('FEEDSUB_SERVICE', 100); // fixme -- avoid hardcoding these?

// We bundle the XML_Parse_Feed library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib');

class FeedSubException extends Exception
{
}

class OStatusPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('main/push/hub', array('action' => 'pushhub'));

        $m->connect('main/push/callback/:feed',
                    array('action' => 'pushcallback'),
                    array('feed' => '[0-9]+'));
        $m->connect('settings/feedsub',
                    array('action' => 'feedsubsettings'));
        return true;
    }

    /**
     * Set up queue handlers for outgoing hub pushes
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        $qm->connect('hubverify', 'HubVerifyQueueHandler');
        $qm->connect('hubdistrib', 'HubDistribQueueHandler');
        $qm->connect('hubout', 'HubOutQueueHandler');
        return true;
    }

    /**
     * Put saved notices into the queue for pubsub distribution.
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        $transports[] = 'hubdistrib';
        return true;
    }

    /**
     * Set up a PuSH hub link to our internal link for canonical timeline
     * Atom feeds for users.
     */
    function onStartApiAtom(Action $action)
    {
        if ($action instanceof ApiTimelineUserAction) {
            $id = $action->arg('id');
            if (strval(intval($id)) === strval($id)) {
                // Canonical form of id in URL?
                // Updates will be handled for our internal PuSH hub.
                $action->element('link', array('rel' => 'hub',
                                               'href' => common_local_url('pushhub')));
            }
        }
        return true;
    }

    /**
     * Add the feed settings page to the Connect Settings menu
     *
     * @param Action &$action The calling page
     *
     * @return boolean hook return
     */
    function onEndConnectSettingsNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('feedsubsettings'),
                          _m('Feeds'),
                          _m('Feed subscription options'),
                          $action_name === 'feedsubsettings');

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        $files = array("$base/classes/$cls.php",
                       "$base/lib/$lower.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    function onCheckSchema() {
        // warning: the autoincrement doesn't seem to set.
        // alter table feedinfo change column id id int(11) not null  auto_increment;
        $schema = Schema::get();
        $schema->ensureTable('feedinfo', Feedinfo::schemaDef());
        $schema->ensureTable('hubsub', HubSub::schemaDef());
        return true;
    }
}
