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

if (!defined('LACONICA')) {
    exit(1);
}

define('TWITTER_SERVICE', 1); // Twitter is foreign_service ID 1

function update_twitter_user($twitter_id, $screen_name)
{
    $uri = 'http://twitter.com/' . $screen_name;
    $fuser = new Foreign_user();

    $fuser->query('BEGIN');

    // Dropping down to SQL because regular DB_DataObject udpate stuff doesn't seem
    // to work so good with tables that have multiple column primary keys

    // Any time we update the uri for a forein user we have to make sure there
    // are no dupe entries first -- unique constraint on the uri column

    $qry = 'UPDATE foreign_user set uri = \'\' WHERE uri = ';
    $qry .= '\'' . $uri . '\'' . ' AND service = ' . TWITTER_SERVICE;

    $fuser->query($qry);

    // Update the user

    $qry = 'UPDATE foreign_user SET nickname = ';
    $qry .= '\'' . $screen_name . '\'' . ', uri = \'' . $uri . '\' ';
    $qry .= 'WHERE id = ' . $twitter_id . ' AND service = ' . TWITTER_SERVICE;

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

    if (empty($result)) {
        common_log(LOG_WARNING,
            "Twitter bridge - removed invalid Twitter user squatting on uri: $new_uri");
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

    if (empty($result)) {
        common_log(LOG_WARNING,
            "Twitter bridge - failed to add new Twitter user: $twitter_id - $screen_name.");
        common_log_db_error($fuser, 'INSERT', __FILE__);
    } else {
        common_debug("Twitter bridge - Added new Twitter user: $screen_name ($twitter_id).");
    }

    return $result;
}

// Creates or Updates a Twitter user
function save_twitter_user($twitter_id, $screen_name)
{

    // Check to see whether the Twitter user is already in the system,
    // and update its screen name and uri if so.

    $fuser = Foreign_user::getForeignUser($twitter_id, TWITTER_SERVICE);

    if (!empty($fuser)) {

        $result = true;

        // Only update if Twitter screen name has changed

        if ($fuser->nickname != $screen_name) {
            $result = update_twitter_user($twitter_id, $screen_name);

            common_debug('Twitter bridge - Updated nickname (and URI) for Twitter user ' .
                "$fuser->id to $screen_name, was $fuser->nickname");
        }

        return $result;

    } else {
        return add_twitter_user($twitter_id, $screen_name);
    }

    $fuser->free();
    unset($fuser);

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

        $user = $flink->getUser();

        // XXX: Hack to get around PHP cURL's use of @ being a a meta character
        $statustxt = preg_replace('/^@/', ' @', $notice->content);

        $token = TwitterOAuthClient::unpackToken($flink->credentials);

        $client = new TwitterOAuthClient($token->key, $token->secret);

        $status = null;

        try {
            $status = $client->statusesUpdate($statustxt);
        } catch (OAuthClientCurlException $e) {

            if ($e->getMessage() == 'The requested URL returned error: 401') {

                $errmsg = sprintf('User %1$s (user id: %2$s) has an invalid ' .
                                  'Twitter OAuth access token.',
                                  $user->nickname, $user->id);
                common_log(LOG_WARNING, $errmsg);

                // Bad auth token! We need to delete the foreign_link
                // to Twitter and inform the user.

                remove_twitter_link($flink);
                return true;

            } else {

                // Some other error happened, so we should probably
                // try to send again later.

                $errmsg = sprintf('cURL error trying to send notice to Twitter ' .
                                  'for user %1$s (user id: %2$s) - ' .
                                  'code: %3$s message: $4$s.',
                                  $user->nickname, $user->id,
                                  $e->getCode(), $e->getMessage());
                common_log(LOG_WARNING, $errmsg);

                return false;
            }
        }

        if (empty($status)) {

            // This could represent a failure posting,
            // or the Twitter API might just be behaving flakey.

            $errmsg = sprint('No data returned by Twitter API when ' .
                             'trying to send update for %1$s (user id %2$s).',
                             $user->nickname, $user->id);
            common_log(LOG_WARNING, $errmsg);

            return false;
        }

        // Notice crossed the great divide

        $msg = sprintf('Twitter bridge posted notice %s to Twitter.',
                       $notice->id);
        common_log(LOG_INFO, $msg);
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

    if (isset($user->email)) {

        $result = mail_twitter_bridge_removed($user);

        if (!$result) {

            $msg = 'Unable to send email to notify ' .
              "$user->nickname (user id: $user->id) " .
              'that their Twitter bridge link was ' .
              'removed!';

            common_log(LOG_WARNING, $msg);
        }
    }

}

