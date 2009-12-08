<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('TWITTER_SERVICE', 1); // Twitter is foreign_service ID 1

require_once INSTALLDIR . '/plugins/TwitterBridge/twitterbasicauthclient.php';
require_once INSTALLDIR . '/plugins/TwitterBridge/twitteroauthclient.php';

function updateTwitter_user($twitter_id, $screen_name)
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
            $result = updateTwitter_user($twitter_id, $screen_name);

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
        if (TwitterOAuthClient::isPackedToken($flink->credentials)) {
            return broadcast_oauth($notice, $flink);
        } else {
            return broadcast_basicauth($notice, $flink);
        }
    }

    return true;
}

function broadcast_oauth($notice, $flink) {
    $user = $flink->getUser();
    $statustxt = format_status($notice);
    // Convert !groups to #hashes
    $statustxt = preg_replace('/(^|\s)!([A-Za-z0-9]{1,64})/', "\\1#\\2", $statustxt);
    $token = TwitterOAuthClient::unpackToken($flink->credentials);
    $client = new TwitterOAuthClient($token->key, $token->secret);
    $status = null;

    try {
        $status = $client->statusesUpdate($statustxt);
    } catch (OAuthClientException $e) {
        return process_error($e, $flink);
    }

    if (empty($status)) {

        // This could represent a failure posting,
        // or the Twitter API might just be behaving flakey.

        $errmsg = sprintf('Twitter bridge - No data returned by Twitter API when ' .
                          'trying to send update for %1$s (user id %2$s).',
                          $user->nickname, $user->id);
        common_log(LOG_WARNING, $errmsg);

        return false;
    }

    // Notice crossed the great divide

    $msg = sprintf('Twitter bridge - posted notice %s to Twitter using OAuth.',
                   $notice->id);
    common_log(LOG_INFO, $msg);

    return true;
}

function broadcast_basicauth($notice, $flink)
{
    $user = $flink->getUser();

    $statustxt = format_status($notice);

    $client = new TwitterBasicAuthClient($flink);
    $status = null;

    try {
        $status = $client->statusesUpdate($statustxt);
    } catch (HTTP_Request2_Exception $e) {
        return process_error($e, $flink);
    }

    if (empty($status)) {

        $errmsg = sprintf('Twitter bridge - No data returned by Twitter API when ' .
                          'trying to send update for %1$s (user id %2$s).',
                          $user->nickname, $user->id);
        common_log(LOG_WARNING, $errmsg);

            $errmsg = sprintf('No data returned by Twitter API when ' .
                             'trying to send update for %1$s (user id %2$s).',
                             $user->nickname, $user->id);
            common_log(LOG_WARNING, $errmsg);
        return false;
    }

    $msg = sprintf('Twitter bridge - posted notice %s to Twitter using basic auth.',
                   $notice->id);
    common_log(LOG_INFO, $msg);

    return true;
}

function process_error($e, $flink)
{
    $user        = $flink->getUser();
    $errmsg      = $e->getMessage();
    $delivered   = false;

    switch($errmsg) {
     case 'The requested URL returned error: 401':
        $logmsg = sprintf('Twiter bridge - User %1$s (user id: %2$s) has an invalid ' .
                          'Twitter screen_name/password combo or an invalid acesss token.',
                          $user->nickname, $user->id);
        $delivered = true;
        remove_twitter_link($flink);
        break;
     case 'The requested URL returned error: 403':
        $logmsg = sprintf('Twitter bridge - User %1$s (user id: %2$s) has exceeded ' .
                          'his/her Twitter request limit.',
                          $user->nickname, $user->id);
        break;
     default:
        $logmsg = sprintf('Twitter bridge - cURL error trying to send notice to Twitter ' .
                          'for user %1$s (user id: %2$s) - ' .
                          'code: %3$s message: %4$s.',
                          $user->nickname, $user->id,
                          $e->getCode(), $e->getMessage());
        break;
    }

    common_log(LOG_WARNING, $logmsg);

    return $delivered;
}

function format_status($notice)
{
    // XXX: Hack to get around PHP cURL's use of @ being a a meta character
    return preg_replace('/^@/', ' @', $notice->content);
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

/**
 * Send a mail message to notify a user that her Twitter bridge link
 * has stopped working, and therefore has been removed.  This can
 * happen when the user changes her Twitter password, or otherwise
 * revokes access.
 *
 * @param User $user   user whose Twitter bridge link has been removed
 *
 * @return boolean success flag
 */

function mail_twitter_bridge_removed($user)
{
    common_init_locale($user->language);

    $profile = $user->getProfile();

    $subject = sprintf(_m('Your Twitter bridge has been disabled.'));

    $site_name = common_config('site', 'name');

    $body = sprintf(_m('Hi, %1$s. We\'re sorry to inform you that your ' .
        'link to Twitter has been disabled. We no longer seem to have ' .
    'permission to update your Twitter status. (Did you revoke ' .
    '%3$s\'s access?)' . "\n\n" .
    'You can re-enable your Twitter bridge by visiting your ' .
    "Twitter settings page:\n\n\t%2\$s\n\n" .
        "Regards,\n%3\$s\n"),
        $profile->getBestName(),
        common_local_url('twittersettings'),
        common_config('site', 'name'));

    common_init_locale();
    return mail_to_user($user, $subject, $body);
}

