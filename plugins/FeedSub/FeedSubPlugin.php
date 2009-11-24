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

class FeedSubPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */

    function onRouterInitialized($m)
    {
        $m->connect('feedsub/callback/:feed',
                    array('action' => 'feedsubcallback'),
                    array('feed' => '[0-9]+'));
        $m->connect('settings/feedsub',
                    array('action' => 'feedsubsettings'));
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
                          dgettext('FeebSubPlugin', 'Feeds'),
                          dgettext('FeedSubPlugin', 'Feed subscription options'),
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
        $files = array("$base/$lower.php");
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

    /*
    // auto increment seems to be broken
    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureDataObject('Feedinfo');
        return true;
    }
    */
}
