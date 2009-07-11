<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

define('TWITTER_SERVICE', 1); // Twitter is foreign_service ID 1

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

        if (defined('SCRIPT_DEBUG')) {
            print "cURL error: $errmsg - trying to load: $uri with user $screen_name.\n";
        }
    }

    curl_close($ch);

    return $data;
}

function twitter_json_data($uri, $screen_name, $password)
{
    $json_data = get_twitter_data($uri, $screen_name, $password);

    if (!$json_data) {
        return false;
    }

    $data = json_decode($json_data);

    if (!$data) {
        return false;
    }

    return $data;
}

function twitter_user_info($screen_name, $password)
{
    $uri = "http://twitter.com/users/show/$screen_name.json";
    return twitter_json_data($uri, $screen_name, $password);
}

function twitter_friends_ids($screen_name, $password)
{
    $uri = "http://twitter.com/friends/ids/$screen_name.json";
    return twitter_json_data($uri, $screen_name, $password);
}

function update_twitter_user($twitter_id, $screen_name)
{
    $uri = 'http://twitter.com/' . $screen_name;

    $fuser = new Foreign_user();

    $fuser->query('BEGIN');

    // Dropping down to SQL because regular db_object udpate stuff doesn't seem
    // to work so good with tables that have multiple column primary keys

    // Any time we update the uri for a forein user we have to make sure there
    // are no dupe entries first -- unique constraint on the uri column

    $qry = 'UPDATE foreign_user set uri = \'\' WHERE uri = ';
    $qry .= '\'' . $uri . '\'' . ' AND service = ' . TWITTER_SERVICE;

    $result = $fuser->query($qry);

    if ($result) {
        common_debug("Removed uri ($uri) from another foreign_user who was squatting on it.");
        if (defined('SCRIPT_DEBUG')) {
            print("Removed uri ($uri) from another Twitter user who was squatting on it.\n");
        }
    }

    // Update the user
    $qry = 'UPDATE foreign_user SET nickname = ';
    $qry .= '\'' . $screen_name . '\'' . ', uri = \'' . $uri . '\' ';
    $qry .= 'WHERE id = ' . $twitter_id . ' AND service = ' . TWITTER_SERVICE;

    $result = $fuser->query($qry);

    if (!$result) {
        common_log(LOG_WARNING,
            "Couldn't update foreign_user data for Twitter user: $screen_name");
        common_log_db_error($fuser, 'UPDATE', __FILE__);
        if (defined('SCRIPT_DEBUG')) {
            print "UPDATE failed: for Twitter user:  $twitter_id - $screen_name. - ";
            print common_log_objstring($fuser) . "\n";
            $error = &PEAR::getStaticProperty('DB_DataObject','lastError');
            print "DB_DataObject Error: " . $error->getMessage() . "\n";
        }
        return false;
    }

    $fuser->query('COMMIT');

    $fuser->free();
    unset($fuser);

    return true;
}

function add_twitter_user($twitter_id, $screen_name)
{

    $new_uri = 'http://twitter.com/' . $screen_name;

    // Clear out any bad old foreign_users with the new user's legit URL
    // This can happen when users move around or fakester accounts get
    // repoed, and things like that.
    $luser = new Foreign_user();
    $luser->uri = $new_uri;
    $luser->service = TWITTER_SERVICE;
    $result = $luser->delete();

    if ($result) {
        common_log(LOG_WARNING,
            "Twitter bridge - removed invalid Twitter user squatting on uri: $new_uri");
        if (defined('SCRIPT_DEBUG')) {
            print "Removed invalid Twitter user squatting on uri: $new_uri\n";
        }
    }

    $luser->free();
    unset($luser);

    // Otherwise, create a new Twitter user
    $fuser = new Foreign_user();

    $fuser->nickname = $screen_name;
    $fuser->uri = 'http://twitter.com/' . $screen_name;
    $fuser->id = $twitter_id;
    $fuser->service = TWITTER_SERVICE;
    $fuser->created = common_sql_now();
    $result = $fuser->insert();

    if (!$result) {
        common_log(LOG_WARNING,
            "Twitter bridge - failed to add new Twitter user: $twitter_id - $screen_name.");
        common_log_db_error($fuser, 'INSERT', __FILE__);
        if (defined('SCRIPT_DEBUG')) {
            print "INSERT failed: could not add new Twitter user: $twitter_id - $screen_name. - ";
            print common_log_objstring($fuser) . "\n";
            $error = &PEAR::getStaticProperty('DB_DataObject','lastError');
            print "DB_DataObject Error: " . $error->getMessage() . "\n";
        }
    } else {
        common_debug("Twitter bridge - Added new Twitter user: $screen_name ($twitter_id).");
        if (defined('SCRIPT_DEBUG')) {
            print "Added new Twitter user: $screen_name ($twitter_id).\n";
        }
    }

    return $result;
}

// Creates or Updates a Twitter user
function save_twitter_user($twitter_id, $screen_name)
{

    // Check to see whether the Twitter user is already in the system,
    // and update its screen name and uri if so.
    $fuser = Foreign_user::getForeignUser($twitter_id, TWITTER_SERVICE);

    if ($fuser) {

        $result = true;

        // Only update if Twitter screen name has changed
        if ($fuser->nickname != $screen_name) {
            $result = update_twitter_user($twitter_id, $screen_name);

            common_debug('Twitter bridge - Updated nickname (and URI) for Twitter user ' .
                "$fuser->id to $screen_name, was $fuser->nickname");

            if (defined('SCRIPT_DEBUG')) {
                print 'Updated nickname (and URI) for Twitter user ' .
                    "$fuser->id to $screen_name, was $fuser->nickname\n";
            }
        }

        return $result;

    } else {
        return add_twitter_user($twitter_id, $screen_name);
    }

    $fuser->free();
    unset($fuser);

    return true;
}

function retreive_twitter_friends($twitter_id, $screen_name, $password)
{
    $friends = array();

    $uri = "http://twitter.com/statuses/friends/$twitter_id.json?page=";
    $friends_ids = twitter_friends_ids($screen_name, $password);

    if (!$friends_ids) {
        return $friends;
    }

    if (defined('SCRIPT_DEBUG')) {
        print "Twitter 'social graph' ids method says $screen_name has " .
            count($friends_ids) . " friends.\n";
    }

    // Calculate how many pages to get...
    $pages = ceil(count($friends_ids) / 100);

    if ($pages == 0) {
        common_log(LOG_WARNING,
            "Twitter bridge - $screen_name seems to have no friends.");
        if (defined('SCRIPT_DEBUG')) {
            print "$screen_name seems to have no friends.\n";
        }
    }

    for ($i = 1; $i <= $pages; $i++) {

        $data = get_twitter_data($uri . $i, $screen_name, $password);

        if (!$data) {
            common_log(LOG_WARNING,
                "Twitter bridge - Couldn't retrieve page $i of $screen_name's friends.");
            if (defined('SCRIPT_DEBUG')) {
                print "Couldn't retrieve page $i of $screen_name's friends.\n";
            }
            continue;
        }

        $more_friends = json_decode($data);

        if (!$more_friends) {

            common_log(LOG_WARNING,
                "Twitter bridge - No data for page $i of $screen_name's friends.");
            if (defined('SCRIPT_DEBUG')) {
                print "No data for page $i of $screen_name's friends.\n";
            }
            continue;
        }

         $friends = array_merge($friends, $more_friends);
    }

    return $friends;
}

function save_twitter_friends($user, $twitter_id, $screen_name, $password)
{

    $friends = retreive_twitter_friends($twitter_id, $screen_name, $password);

    if (empty($friends)) {
        common_debug("Twitter bridge - Couldn't get friends data from Twitter for $screen_name.");
        if (defined('SCRIPT_DEBUG')) {
            print "Couldn't get friends data from Twitter for $screen_name.\n";
        }
        return false;
    }

    foreach ($friends as $friend) {

        $friend_name = $friend->screen_name;
        $friend_id = (int) $friend->id;

        // Update or create the Foreign_user record
        if (!save_twitter_user($friend_id, $friend_name)) {
            common_log(LOG_WARNING,
                "Twitter bridge - couldn't save $screen_name's friend, $friend_name.");
            if (defined('SCRIPT_DEBUG')) {
                print "Couldn't save $screen_name's friend, $friend_name.\n";
            }
            continue;
        }

        // Check to see if there's a related local user
        $flink = Foreign_link::getByForeignID($friend_id, 1);

        if ($flink) {

            // Get associated user and subscribe her
            $friend_user = User::staticGet('id', $flink->user_id);
            if (!empty($friend_user)) {
                $result = subs_subscribe_to($user, $friend_user);

                if ($result === true) {
                    common_debug("Twitter bridge - subscribed $friend_user->nickname to $user->nickname.");
                    if (defined('SCRIPT_DEBUG')) {
                        print("Subscribed $friend_user->nickname to $user->nickname.\n");
                    }
                } else {
                    if (defined('SCRIPT_DEBUG')) {
                        print "$result ($friend_user->nickname to $user->nickname)\n";
                    }
                }
            }
        }
    }

    return true;
}

function is_twitter_bound($notice, $flink) {

    // Check to see if notice should go to Twitter
    if (!empty($flink) && ($flink->noticesync & FOREIGN_NOTICE_SEND)) {

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

    $flink = Foreign_link::getByUserID($notice->profile_id,
        TWITTER_SERVICE);

    if (is_twitter_bound($notice, $flink)) {

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
        $errno = curl_errno($ch);

        if (!empty($errmsg)) {
            common_debug("cURL error ($errno): $errmsg - " .
                "trying to send notice for $twitter_user.",
                         __FILE__);

            $user = $flink->getUser();

            if ($errmsg == 'The requested URL returned error: 401') {
                common_debug(sprintf('User %s (user id: %s) ' .
                    'has bad Twitter credentials!',
                    $user->nickname, $user->id));

                    // Bad credentials we need to delete the foreign_link
                    // to Twitter and inform the user.

                    remove_twitter_link($flink);

                    return true;

            } else {

                // Some other error happened, so we should try to
                // send again later

                return false;
            }

        }

        curl_close($ch);

        if (empty($data)) {
            common_debug("No data returned by Twitter's " .
                "API trying to send update for $twitter_user",
                         __FILE__);

            // XXX: Not sure this represents a failure to send, but it
            // probably does

            return false;

        } else {

            // Twitter should return a status
            $status = json_decode($data);

            if (empty($status)) {
                common_debug("Unexpected data returned by Twitter " .
                    " API trying to send update for $twitter_user",
                        __FILE__);

                // XXX: Again, this could represent a failure posting
                // or the Twitter API might just be behaving flakey.
                // We're treating it as a failure to post.

                return false;
            }
        }
    }

    return true;
}

function remove_twitter_link($flink)
{
    $user = $flink->getUser();

    common_log(LOG_INFO, 'Removing Twitter bridge Foreign link for ' .
        "user $user->nickname (user id: $user->id).");

    $result = $flink->delete();

    if (empty($result)) {
        common_log(LOG_ERR, 'Could not remove Twitter bridge ' .
            "Foreign_link for $user->nickname (user id: $user->id)!");
        common_log_db_error($flink, 'DELETE', __FILE__);
    }

    // Notify the user that her Twitter bridge is down

    $result = mail_twitter_bridge_removed($user);

    if (!$result) {

        $msg = 'Unable to send email to notify ' .
            "$user->nickname (user id: $user->id) " .
            'that their Twitter bridge link was ' .
            'removed!';

        common_log(LOG_WARNING, $msg);
    }

}

