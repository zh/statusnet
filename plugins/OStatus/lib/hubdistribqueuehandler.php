<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * Send a PuSH subscription verification from our internal hub.
 * Queue up final distribution for 
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubDistribQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'hubdistrib';
    }

    function handle($notice)
    {
        assert($notice instanceof Notice);

        $this->pushUser($notice);
        foreach ($notice->getGroups() as $group) {
            $this->pushGroup($notice, $group->group_id);
        }
        return true;
    }
    
    function pushUser($notice)
    {
        // See if there's any PuSH subscriptions, including OStatus clients.
        // @fixme handle group subscriptions as well
        // http://identi.ca/api/statuses/user_timeline/1.atom
        $feed = common_local_url('ApiTimelineUser',
                                 array('id' => $notice->profile_id,
                                       'format' => 'atom'));
        $this->pushFeed($feed, array($this, 'userFeedForNotice'), $notice);
    }

    function pushGroup($notice, $group_id)
    {
        $feed = common_local_url('ApiTimelineGroup',
                                 array('id' => $group_id,
                                       'format' => 'atom'));
        $this->pushFeed($feed, array($this, 'groupFeedForNotice'), $group_id, $notice);
    }

    /**
     * @param string $feed URI to the feed
     * @param callable $callback function to generate Atom feed update if needed
     *        any additional params are passed to the callback.
     */
    function pushFeed($feed, $callback)
    {
        $hub = common_config('ostatus', 'hub');
        if ($hub) {
            $this->pushFeedExternal($feed, $hub);
        }

        $sub = new HubSub();
        $sub->topic = $feed;
        if ($sub->find()) {
            $args = array_slice(func_get_args(), 2);
            $atom = call_user_func_array($callback, $args);
            $this->pushFeedInternal($atom, $sub);
        } else {
            common_log(LOG_INFO, "No PuSH subscribers for $feed");
        }
        return true;
    }

    /**
     * Ping external hub about this update.
     * The hub will pull the feed and check for new items later.
     * Not guaranteed safe in an environment with database replication.
     *
     * @param string $feed feed topic URI
     * @param string $hub PuSH hub URI
     * @fixme can consolidate pings for user & group posts
     */
    function pushFeedExternal($feed, $hub)
    {
        $client = new HTTPClient();
        try {
            $data = array('hub.mode' => 'publish',
                          'hub.url' => $feed);
            $response = $client->post($hub, array(), $data);
            if ($response->getStatus() == 204) {
                common_log(LOG_INFO, "PuSH ping to hub $hub for $feed ok");
                return true;
            } else {
                common_log(LOG_ERR, "PuSH ping to hub $hub for $feed failed with HTTP " .
                                    $response->getStatus() . ': ' .
                                    $response->getBody());
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, "PuSH ping to hub $hub for $feed failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue up direct feed update pushes to subscribers on our internal hub.
     * @param string $atom update feed, containing only new/changed items
     * @param HubSub $sub open query of subscribers
     */
    function pushFeedInternal($atom, $sub)
    {
        common_log(LOG_INFO, "Preparing $sub->N PuSH distribution(s) for $sub->topic");
        $qm = QueueManager::get();
        while ($sub->fetch()) {
            $sub->distribute($atom);
        }
    }

    /**
     * Build a single-item version of the sending user's Atom feed.
     * @param Notice $notice
     * @return string
     */
    function userFeedForNotice($notice)
    {
        // @fixme this feels VERY hacky...
        // should probably be a cleaner way to do it

        ob_start();
        $api = new ApiTimelineUserAction();
        $api->prepare(array('id' => $notice->profile_id,
                            'format' => 'atom',
                            'max_id' => $notice->id,
                            'since_id' => $notice->id - 1));
        $api->showTimeline();
        $feed = ob_get_clean();
        
        // ...and override the content-type back to something normal... eww!
        // hope there's no other headers that got set while we weren't looking.
        header('Content-Type: text/html; charset=utf-8');

        common_log(LOG_DEBUG, $feed);
        return $feed;
    }

    function groupFeedForNotice($group_id, $notice)
    {
        // @fixme this feels VERY hacky...
        // should probably be a cleaner way to do it

        ob_start();
        $api = new ApiTimelineGroupAction();
        $args = array('id' => $group_id,
                      'format' => 'atom',
                      'max_id' => $notice->id,
                      'since_id' => $notice->id - 1);
        $api->prepare($args);
        $api->handle($args);
        $feed = ob_get_clean();
        
        // ...and override the content-type back to something normal... eww!
        // hope there's no other headers that got set while we weren't looking.
        header('Content-Type: text/html; charset=utf-8');

        common_log(LOG_DEBUG, $feed);
        return $feed;
    }

}

