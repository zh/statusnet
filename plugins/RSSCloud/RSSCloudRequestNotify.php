<?php

/**
 * Action to let RSSCloud aggregators request update notification when
 * user profile feeds change.
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('STATUSNET')) {
    exit(1);
}

class RSSCloudRequestNotifyAction extends Action
{
    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->ip        = $_SERVER['REMOTE_ADDR'];
        $this->port      = $this->arg('port');
        $this->path      = $this->arg('path');
        $this->protocol  = $this->arg('protocol');
        $this->procedure = $this->arg('notifyProcedure');
        $this->feeds     = $this->getFeeds();

        $this->subscriber_url = 'http://' . $this->ip . ':' . $this->port . $this->path;

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->showResult(false, 'Request must be POST.');
            return;
        }

        $missing = array();

        if (empty($this->port)) {
            $missing[] = 'port';
        }

        $path = $this->arg('path');

        if (empty($this->path)) {
            $missing[] = 'path';
        }

        $protocol = $this->arg('protocol');

        if (empty($this->protocol)) {
            $missing[] = 'protocol';
        }

        if (!isset($this->procedure)) {
            $missing[] = 'notifyProcedure';
        }

        if (!empty($missing)) {
            $msg = 'The following parameters were missing from the request body: ' .
                implode(', ', $missing) . '.';
            $this->showResult(false, $msg);
            return;
        }

        if (empty($this->feeds)) {
            $this->showResult(false,
                              'You must provide at least one valid profile feed url (url1, url2, url3 ... urlN).');
            return;
        }

        $endpoint = $ip . ':' . $port . $path;

        foreach ($this->feeds as $feed) {
            $this->saveSubscription($feed);
        }

        // XXX: What to do about deleting stale subscriptions?  25 hours seems harsh.
        // WordPress doesn't ever remove subscriptions.

        $msg = 'Thanks for the registration. It worked. When the feed(s) update(s) we\'ll notify you. ' .
               ' Don\'t forget to re-register after 24 hours, your subscription will expire in 25.';

        $this->showResult(true, $msg);
    }

    function getFeeds()
    {
        $feeds = array();

        foreach ($this->args as $key => $feed ) {
            if (preg_match('|url\d+|', $key)) {

                if ($this->testFeed($feed)) {
                    $feeds[] = $feed;
                } else {
                    $msg = 'RSSCloud Plugin - ' . $this->ip . ' tried to subscribe ' .
                           'to a non-existent feed: ' . $feed;
                    common_log(LOG_WARN, $msg);
                }
            }
        }

        return $feeds;
    }

    function testNotificationHandler($feed)
    {
        $notifier = new RSSCloudNotifier();
        return $notifier->postUpdate($endpoint, $feed);
    }

    // returns valid user or false
    function testFeed($feed)
    {
        $user = $this->userFromFeed($feed);

        if (!empty($user)) {

            common_debug("Valid feed: $feed");

            // OK, so this is a valid profile feed url, now let's see if the
            // other system reponds to our notifications before we
            // add the sub...

            if ($this->testNotificationHandler($feed)) {
                return true;
            }
        }

        return false;
    }

    // this actually does the validating and figuring out the
    // user, which it returns
    function userFromFeed($feed)
    {
        // We only do profile feeds

        $path = common_path('api/statuses/user_timeline/');
        $valid = '%^' . $path . '(?<nickname>.*)\.rss$%';

        if (preg_match($valid, $feed, $matches)) {
            $user = User::staticGet('nickname', $matches['nickname']);
            if (!empty($user)) {
                return $user;
            }
        }

        return false;
    }

    function saveSubscription($feed)
    {
        // check to see if we already have a profile for this subscriber

        $other = Remote_profile::staticGet('uri', $this->subscriber_url);

        if ($other === false) {
            $other->saveProfile();
        }

        $user = userFromFeed($feed);

        $result = subs_subscribe_to($user, $other);

        if ($result != true) {
            $msg = "RSSPlugin - got '$result' trying to subscribe " .
                   "$this->subscriber_url to $user->nickname" . "'s profile feed.";
            common_log(LOG_WARN, $msg);
        } else {
            $msg = 'RSSCloud plugin - subscribe: ' . $this->subscriber_url .
                   ' subscribed to ' . $feed;

            common_log(LOG_INFO, $msg);
        }
    }

    function saveProfile()
    {
        common_debug("Saving remote profile for $this->subscriber_url");

        // XXX: We need to add a field to Remote_profile to indicate the kind
        // of remote profile?  i.e: OMB, RSSCloud, PuSH, Twitter

        $remote                = new Remote_profile();
        $remote->uri           = $this->subscriber_url;
        $remote->postnoticeurl = $this->subscriber_url;
        $remote->created       = DB_DataObject_Cast::dateTime();

        if (!$remote->insert()) {
            throw new Exception(_('RSSCloud plugin - Error inserting remote profile!'));
        }
    }

    function showResult($success, $msg)
    {
        $this->startXML();
        $this->elementStart('notifyResult', array('success' => ($success) ? 'true' : 'false',
                                                  'msg'     => $msg));
        $this->endXML();
    }

}



