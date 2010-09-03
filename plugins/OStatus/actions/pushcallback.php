<?php
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


class PushCallbackAction extends Action
{
    function handle()
    {
        StatusNet::setApi(true); // Minimize error messages to aid in debugging
        parent::handle();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->handleGet();
        }
    }

    /**
     * Handler for POST content updates from the hub
     */
    function handlePost()
    {
        $feedid = $this->arg('feed');
        common_log(LOG_INFO, "POST for feed id $feedid");
        if (!$feedid) {
            throw new ServerException('Empty or invalid feed id.', 400);
        }

        $feedsub = FeedSub::staticGet('id', $feedid);
        if (!$feedsub) {
            // @todo i18n FIXME: added i18n and use sprintf when using parameters.
            throw new ServerException('Unknown PuSH feed id ' . $feedid, 400);
        }

        $hmac = '';
        if (isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
            $hmac = $_SERVER['HTTP_X_HUB_SIGNATURE'];
        }

        $post = file_get_contents('php://input');

        // Queue this to a background process; we should return
        // as quickly as possible from a distribution POST.
        // If queues are disabled this'll process immediately.
        $data = array('feedsub_id' => $feedsub->id,
                      'post' => $post,
                      'hmac' => $hmac);
        $qm = QueueManager::get();
        $qm->enqueue($data, 'pushin');
    }

    /**
     * Handler for GET verification requests from the hub.
     */
    function handleGet()
    {
        $mode = $this->arg('hub_mode');
        $topic = $this->arg('hub_topic');
        $challenge = $this->arg('hub_challenge');
        $lease_seconds = $this->arg('hub_lease_seconds');
        $verify_token = $this->arg('hub_verify_token');

        if ($mode != 'subscribe' && $mode != 'unsubscribe') {
            throw new ClientException("Bad hub.mode $mode", 404);
        }

        $feedsub = FeedSub::staticGet('uri', $topic);
        if (!$feedsub) {
            // @todo i18n FIXME: added i18n and use sprintf when using parameters.
            throw new ClientException("Bad hub.topic feed $topic.", 404);
        }

        if ($feedsub->verify_token !== $verify_token) {
            // @todo i18n FIXME: added i18n and use sprintf when using parameters.
            throw new ClientException("Bad hub.verify_token $token for $topic.", 404);
        }

        if ($mode == 'subscribe') {
            // We may get re-sub requests legitimately.
            if ($feedsub->sub_state != 'subscribe' && $feedsub->sub_state != 'active') {
                // @todo i18n FIXME: added i18n and use sprintf when using parameters.
                throw new ClientException("Unexpected subscribe request for $topic.", 404);
            }
        } else {
            if ($feedsub->sub_state != 'unsubscribe') {
                // @todo i18n FIXME: added i18n and use sprintf when using parameters.
                throw new ClientException("Unexpected unsubscribe request for $topic.", 404);
            }
        }

        if ($mode == 'subscribe') {
            if ($feedsub->sub_state == 'active') {
                common_log(LOG_INFO, __METHOD__ . ': sub update confirmed');
            } else {
                common_log(LOG_INFO, __METHOD__ . ': sub confirmed');
            }
            $feedsub->confirmSubscribe($lease_seconds);
        } else {
            common_log(LOG_INFO, __METHOD__ . ": unsub confirmed; deleting sub record for $topic");
            $feedsub->confirmUnsubscribe();
        }
        print $challenge;
    }
}
