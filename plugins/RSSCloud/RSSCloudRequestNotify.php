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
        $this->domain    = $this->arg('domain');
        
        $this->feeds     = $this->getFeeds();

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

        // We have to validate everything before saving anything.
        // We only return one success or failure no matter how 
        // many feeds the subscriber is trying to subscribe to

        foreach ($this->feeds as $feed) {
            
            if (!$this->validateFeed($feed)) {
                $msg = 'Feed subscription failed - Not a valid feed.';
                $this->showResult(false, $msg);
                return;
            }
            
            if (!$this->testNotificationHandler($feed)) {
                $msg = 'Feed subscription failed - ' .
                'notification handler doesn\'t respond correctly.';
                $this->showResult(false, $msg);
                return;  
            }
            
        }

        foreach ($this->feeds as $feed) {
            $this->saveSubscription($feed);
        } 

        // XXX: What to do about deleting stale subscriptions?  25 hours seems harsh.
        // WordPress doesn't ever remove subscriptions.

        $msg = 'Thanks for the registration. It worked. When the feed(s) update(s) we\'ll notify you. ' .
               ' Don\'t forget to re-register after 24 hours, your subscription will expire in 25.';

        $this->showResult(true, $msg);        
    }

    function validateFeed($feed)
    {
        $user = $this->userFromFeed($feed);

        if (empty($user)) {
            return false;
        }

        return true;
    }


    function getFeeds()
    {
        $feeds = array();
            
        while (list($key, $feed) = each ($this->args)) {            
            if (preg_match('/^url\d*$/', $key)) {
                $feeds[] = $feed;
            } 
        }

        return $feeds;
    }

    function testNotificationHandler($feed)
    {        
        common_debug("RSSCloudPlugin - testNotificationHandler()");
        
        $notifier = new RSSCloudNotifier();
        
        if (isset($this->domain)) {
            
            //get
            
            $this->url = 'http://' . $this->domain . ':' . $this->port . '/' . $this->path;
            
            common_debug('domain set need to send challenge');
            
        } else {
            
            //post
            
            $this->url = 'http://' . $this->ip . ':' . $this->port . '/' . $this->path;
            
            //return $notifier->postUpdate($endpoint, $feed);

        }   

        return true;

    }

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
        $user = $this->userFromFeed($feed);
        
        common_debug('user = ' . $user->id);
        
        $sub = RSSCloudSubscription::getSubscription($user->id, $this->url);
        
        if ($sub) {
            common_debug("already subscribed to that!");
        } else {
            common_debug('No feed for user ' . $user->id . ' notify: ' . $this->url);
        }
        
        common_debug('RSSPlugin - saveSubscription');
        // turn debugging high
        DB_DataObject::debugLevel(5);
        
        $sub = new RSSCloudSubscription();
        
        $sub->subscribed = $user->id;
        $sub->url        = $this->url;
        $sub->created    = common_sql_now();
        
        // auto timestamp doesn't seem to work for me
        
        $sub->modified   = common_sql_now();
        
        if (!$sub->insert()) {
            common_log_db_error($sub, 'INSERT', __FILE__);
            return false;
        }
        DB_DataObject::debugLevel();
        
        return true;
    }

    function showResult($success, $msg)
    {
        $this->startXML();
        $this->elementStart('notifyResult', array('success' => ($success) ? 'true' : 'false',
                                                  'msg'     => $msg));
        $this->endXML();
    }

}



