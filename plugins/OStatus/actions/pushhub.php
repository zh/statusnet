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
 * Integrated PuSH hub; lets us only ping them what need it.
 * @package Hub
 * @maintainer Brion Vibber <brion@status.net>
 */

/**


Things to consider...
* should we purge incomplete subscriptions that never get a verification pingback?
* when can we send subscription renewal checks?
    - at next send time probably ok
* when can we handle trimming of subscriptions?
    - at next send time probably ok
* should we keep a fail count?

*/


class PushHubAction extends Action
{
    function arg($arg, $def=null)
    {
        // PHP converts '.'s in incoming var names to '_'s.
        // It also merges multiple values, which'll break hub.verify and hub.topic for publishing
        // @fixme handle multiple args
        $arg = str_replace('hub.', 'hub_', $arg);
        return parent::arg($arg, $def);
    }

    function prepare($args)
    {
        StatusNet::setApi(true); // reduce exception reports to aid in debugging
        return parent::prepare($args);
    }

    function handle()
    {
        $mode = $this->trimmed('hub.mode');
        switch ($mode) {
        case "subscribe":
        case "unsubscribe":
            $this->subunsub($mode);
            break;
        case "publish":
            throw new ClientException("Publishing outside feeds not supported.", 400);
        default:
            throw new ClientException("Unrecognized mode '$mode'.", 400);
        }
    }

    /**
     * Process a request for a new or modified PuSH feed subscription.
     * If asynchronous verification is requested, updates won't be saved immediately.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   400 Bad Request - rejecting this (not specifically spec'd)
     */
    function subunsub($mode)
    {
        $callback = $this->argUrl('hub.callback');

        $topic = $this->argUrl('hub.topic');
        if (!$this->recognizedFeed($topic)) {
            throw new ClientException("Unsupported hub.topic $topic; this hub only serves local user and group Atom feeds.");
        }

        $verify = $this->arg('hub.verify'); // @fixme may be multiple
        if ($verify != 'sync' && $verify != 'async') {
            throw new ClientException("Invalid hub.verify $verify; must be sync or async.");
        }

        $lease = $this->arg('hub.lease_seconds', null);
        if ($mode == 'subscribe' && $lease != '' && !preg_match('/^\d+$/', $lease)) {
            throw new ClientException("Invalid hub.lease $lease; must be empty or positive integer.");
        }

        $token = $this->arg('hub.verify_token', null);

        $secret = $this->arg('hub.secret', null);
        if ($secret != '' && strlen($secret) >= 200) {
            throw new ClientException("Invalid hub.secret $secret; must be under 200 bytes.");
        }

        $sub = HubSub::staticGet($topic, $callback);
        if (!$sub) {
            // Creating a new one!
            $sub = new HubSub();
            $sub->topic = $topic;
            $sub->callback = $callback;
        }
        if ($mode == 'subscribe') {
            if ($secret) {
                $sub->secret = $secret;
            }
            if ($lease) {
                $sub->setLease(intval($lease));
            }
        }

        if (!common_config('queue', 'enabled')) {
            // Won't be able to background it.
            $verify = 'sync';
        }
        if ($verify == 'async') {
            $sub->scheduleVerify($mode, $token);
            header('HTTP/1.1 202 Accepted');
        } else {
            $sub->verify($mode, $token);
            header('HTTP/1.1 204 No Content');
        }
    }

    /**
     * Check whether the given URL represents one of our canonical
     * user or group Atom feeds.
     *
     * @param string $feed URL
     * @return boolean true if it matches
     */
    function recognizedFeed($feed)
    {
        $matches = array();
        if (preg_match('!/(\d+)\.atom$!', $feed, $matches)) {
            $id = $matches[1];
            $params = array('id' => $id, 'format' => 'atom');
            $userFeed = common_local_url('ApiTimelineUser', $params);
            $groupFeed = common_local_url('ApiTimelineGroup', $params);

            if ($feed == $userFeed) {
                $user = User::staticGet('id', $id);
                if (!$user) {
                    throw new ClientException("Invalid hub.topic $feed; user doesn't exist.");
                } else {
                    return true;
                }
            }
            if ($feed == $groupFeed) {
                $user = User_group::staticGet('id', $id);
                if (!$user) {
                    throw new ClientException("Invalid hub.topic $feed; group doesn't exist.");
                } else {
                    return true;
                }
            }
            common_log(LOG_DEBUG, "Not a user or group feed? $feed $userFeed $groupFeed");
        }
        common_log(LOG_DEBUG, "LOST $feed");
        return false;
    }

    /**
     * Grab and validate a URL from POST parameters.
     * @throws ClientException for malformed or non-http/https URLs
     */
    protected function argUrl($arg)
    {
        $url = $this->arg($arg);
        $params = array('domain_check' => false, // otherwise breaks my local tests :P
                        'allowed_schemes' => array('http', 'https'));
        if (Validate::uri($url, $params)) {
            return $url;
        } else {
            throw new ClientException("Invalid URL passed for $arg: '$url'");
        }
    }

    /**
     * Get HubSub subscription record for a given feed & subscriber.
     *
     * @param string $feed
     * @param string $callback
     * @return mixed HubSub or false
     */
    protected function getSub($feed, $callback)
    {
        return HubSub::staticGet($feed, $callback);
    }
}

