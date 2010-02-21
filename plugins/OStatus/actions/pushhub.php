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
            $this->subscribe();
            break;
        case "unsubscribe":
            $this->unsubscribe();
            break;
        case "publish":
            throw new ServerException("Publishing outside feeds not supported.", 400);
        default:
            throw new ServerException("Unrecognized mode '$mode'.", 400);
        }
    }

    /**
     * Process a PuSH feed subscription request.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   403 Forbidden - rejecting this (not specifically spec'd)
     */
    function subscribe()
    {
        $feed = $this->argUrl('hub.topic');
        $callback = $this->argUrl('hub.callback');
        $token = $this->arg('hub.verify_token', null);

        common_log(LOG_DEBUG, __METHOD__ . ": checking sub'd to $feed $callback");
        if ($this->getSub($feed, $callback)) {
            // Already subscribed; return 204 per spec.
            header('HTTP/1.1 204 No Content');
            common_log(LOG_DEBUG, __METHOD__ . ': already subscribed');
            return;
        }

        common_log(LOG_DEBUG, __METHOD__ . ': setting up');
        $sub = new HubSub();
        $sub->topic = $feed;
        $sub->callback = $callback;
        $sub->secret = $this->arg('hub.secret', null);
        if (strlen($sub->secret) > 200) {
            throw new ClientException("hub.secret must be no longer than 200 chars", 400);
        }
        $sub->setLease(intval($this->arg('hub.lease_seconds')));

        // @fixme check for feeds we don't manage
        // @fixme check the verification mode, might want a return immediately?

        common_log(LOG_DEBUG, __METHOD__ . ': inserting');
        $ok = $sub->insert();
        
        if (!$ok) {
            throw new ServerException("Failed to save subscription record", 500);
        }

        // @fixme check errors ;)

        $data = array('sub' => $sub, 'mode' => 'subscribe', 'token' => $token);
        $qm = QueueManager::get();
        $qm->enqueue($data, 'hubverify');
        
        header('HTTP/1.1 202 Accepted');
        common_log(LOG_DEBUG, __METHOD__ . ': done');
    }

    /**
     * Process a PuSH feed unsubscription request.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   400 Bad Request - invalid params or rejected feed
     *
     * @fixme background this
     */
    function unsubscribe()
    {
        $feed = $this->argUrl('hub.topic');
        $callback = $this->argUrl('hub.callback');
        $sub = $this->getSub($feed, $callback);
        
        if ($sub) {
            $token = $this->arg('hub.verify_token', null);
            if ($sub->verify('unsubscribe', $token)) {
                $sub->delete();
                common_log(LOG_INFO, "PuSH unsubscribed $feed for $callback");
            } else {
                throw new ServerException("Failed PuSH unsubscription: verification failed! $feed for $callback");
            }
        } else {
            throw new ServerException("Failed PuSH unsubscription: not subscribed! $feed for $callback");
        }
    }

    /**
     * Grab and validate a URL from POST parameters.
     * @throws ServerException for malformed or non-http/https URLs
     */
    protected function argUrl($arg)
    {
        $url = $this->arg($arg);
        $params = array('domain_check' => false, // otherwise breaks my local tests :P
                        'allowed_schemes' => array('http', 'https'));
        if (Validate::uri($url, $params)) {
            return $url;
        } else {
            throw new ServerException("Invalid URL passed for $arg: '$url'", 400);
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

