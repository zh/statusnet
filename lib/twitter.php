<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

if (!defined('LACONICA')) { exit(1); }

define("TWITTER_SERVICE", 1); // Twitter is foreign_service ID 1

function get_twitter_data($uri, $screen_name, $password)
{

    $options = array(
            CURLOPT_USERPWD => sprintf("%s:%s", $screen_name, $password),
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_FAILONERROR        => true,
            CURLOPT_HEADER            => false,
            CURLOPT_FOLLOWLOCATION    => true,
            CURLOPT_USERAGENT      => "Laconica",
            CURLOPT_CONNECTTIMEOUT    => 120,
            CURLOPT_TIMEOUT            => 120,
            # Twitter is strict about accepting invalid "Expect" headers
            CURLOPT_HTTPHEADER => array('Expect:')
    );

    $ch = curl_init($uri);
    curl_setopt_array($ch, $options);
    $data = curl_exec($ch);
    $errmsg = curl_error($ch);

    if ($errmsg) {
        common_debug("Twitter bridge - cURL error: $errmsg - trying to load: $uri with user $screen_name.",
            __FILE__);
    }

    curl_close($ch);

    return $data;
}

function twitter_user_info($screen_name, $password)
{

    $uri = "http://twitter.com/users/show/$screen_name.json";
    $data = get_twitter_data($uri, $screen_name, $password);

    if (!$data) {
        return false;
    }

    $twit_user = json_decode($data);

    if (!$twit_user) {
        return false;
    }

    return $twit_user;
}

function update_twitter_user($fuser, $twitter_id, $screen_name)
{

    $original = clone($fuser);
    $fuser->nickname = $screen_name;
    $fuser->uri = 'http://twitter.com/' . $screen_name;
    $result = $fuser->updateKeys($original);

    if (!$result) {
        common_log_db_error($fuser, 'UPDATE', __FILE__);
        return false;
    }

    return true;
}

function add_twitter_user($twitter_id, $screen_name)
{

    // Otherwise, create a new Twitter user
    $fuser = DB_DataObject::factory('foreign_user');

    $fuser->nickname = $screen_name;
    $fuser->uri = 'http://twitter.com/' . $screen_name;
    $fuser->id = $twitter_id;
    $fuser->service = TWITTER_SERVICE; // Twitter
    $fuser->created = common_sql_now();
    $result = $fuser->insert();

    if (!$result) {
        common_debug("Twitter bridge - failed to add new Twitter user: $twitter_id - $screen_name.");
        common_log_db_error($fuser, 'INSERT', __FILE__);
        return false;
    }

    common_debug("Twitter bridge - Added new Twitter user: $screen_name ($twitter_id).");

    return true;
}

// Creates or Updates a Twitter user
function save_twitter_user($twitter_id, $screen_name)
{

    // Check to see whether the Twitter user is already in the system,
    // and update its screen name and uri if so.
    $fuser = Foreign_user::getForeignUser($twitter_id, 1);

    if ($fuser) {

        // Only update if Twitter screen name has changed
        if ($fuser->nickname != $screen_name) {

            common_debug('Twitter bridge - Updated nickname (and URI) for Twitter user ' .
                "$fuser->id to $screen_name, was $fuser->nickname");

            return update_twitter_user($fuser, $twitter_id, $screen_name);
        }

    } else {
        return add_twitter_user($twitter_id, $screen_name);
    }

    return true;
}

function retreive_twitter_friends($twitter_id, $screen_name, $password)
{

    $uri = "http://twitter.com/statuses/friends/$twitter_id.json?page=";
    $twitter_user = twitter_user_info($screen_name, $password);

    // Calculate how many pages to get...
    $pages = ceil($twitter_user->friends_count / 100);

    if ($pages == 0) {
        common_debug("Twitter bridge - Twitter user $screen_name has no friends! Lame.");
    }

    $friends = array();

    for ($i = 1; $i <= $pages; $i++) {

        $data = get_twitter_data($uri . $i, $screen_name, $password);

        if (!$data) {
            return null;
        }

        $more_friends = json_decode($data);

        if (!$more_friends) {
            return null;
        }

         $friends = array_merge($friends, $more_friends);
    }

    return $friends;
}

function save_twitter_friends($user, $twitter_id, $screen_name, $password)
{

    $friends = retreive_twitter_friends($twitter_id, $screen_name, $password);

    if (is_null($friends)) {
        common_debug("Twitter bridge - Couldn't get friends data from Twitter.");
        return false;
    }

    foreach ($friends as $friend) {

        $friend_name = $friend->screen_name;
        $friend_id = $friend->id;

        // Update or create the Foreign_user record
        if (!save_twitter_user($friend_id, $friend_name)) {
            return false;
        }

        // Check to see if there's a related local user
        $flink = Foreign_link::getByForeignID($friend_id, 1);

        if ($flink) {

            // Get associated user and subscribe her
            $friend_user = User::staticGet('id', $flink->user_id);
            subs_subscribe_to($user, $friend_user);
            common_debug("Twitter bridge - subscribed $friend_user->nickname to $user->nickname.");
        }
    }

    return true;
}

function is_twitter_bound($notice, $flink) {

    // Check to see if notice should go to Twitter
    if ($flink->noticesync & FOREIGN_NOTICE_SEND) {

        // If it's not a Twitter-style reply, or if the user WANTS to send replies.
        if (!preg_match('/^@[a-zA-Z0-9_]{1,15}\b/u', $notice->content) ||
            ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY)) {
                return true;
        }
    }
    
    return false;
}

function broadcast_twitter($notice)
{
    $success = true;

    $flink = Foreign_link::getByUserID($notice->profile_id, 
        TWITTER_SERVICE);
            
    // XXX: Not sure WHERE to check whether a notice should go to 
    // Twitter. Should we even put in the queue if it shouldn't? --Zach
    if (!is_null($flink) && is_twitter_bound($notice, $flink)) {

        $fuser = $flink->getForeignUser();
        $twitter_user = $fuser->nickname;
        $twitter_password = $flink->credentials;
        $uri = 'http://www.twitter.com/statuses/update.json';

        // XXX: Hack to get around PHP cURL's use of @ being a a meta character
        $statustxt = preg_replace('/^@/', ' @', $notice->content);

        $options = array(
            CURLOPT_USERPWD        => "$twitter_user:$twitter_password",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 
                array(
                        'status' => $statustxt,
                        'source' => common_config('integration', 'source')
                     ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => "Laconica",
            CURLOPT_CONNECTTIMEOUT => 120,  // XXX: How long should this be?
            CURLOPT_TIMEOUT        => 120,

            # Twitter is strict about accepting invalid "Expect" headers
            CURLOPT_HTTPHEADER => array('Expect:')
            );

        $ch = curl_init($uri);
        curl_setopt_array($ch, $options);
        $data = curl_exec($ch);
        $errmsg = curl_error($ch);

        if ($errmsg) {
            common_debug("cURL error: $errmsg - " .
                "trying to send notice for $twitter_user.",
                         __FILE__);
            $success = false;
        }

        curl_close($ch);

        if (!$data) {
            common_debug("No data returned by Twitter's " .
                "API trying to send update for $twitter_user",
                         __FILE__);
            $success = false;
        }

        // Twitter should return a status
        $status = json_decode($data);

        if (!$status->id) {
            common_debug("Unexpected data returned by Twitter " .
                " API trying to send update for $twitter_user",
                         __FILE__);
            $success = false;
        }
    }
    
    return $success;
}

