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

require_once INSTALLDIR.'/extlib/facebook/facebook.php';
require_once INSTALLDIR.'/lib/facebookaction.php';
require_once INSTALLDIR.'/lib/noticelist.php';

define("FACEBOOK_SERVICE", 2); // Facebook is foreign_service ID 2
define("FACEBOOK_NOTICE_PREFIX", 1);
define("FACEBOOK_PROMPTED_UPDATE_PREF", 2);

function getFacebook()
{
    static $facebook = null;

    $apikey = common_config('facebook', 'apikey');
    $secret = common_config('facebook', 'secret');

    if ($facebook === null) {
        $facebook = new Facebook($apikey, $secret);
    }

    if (!$facebook) {
        common_log(LOG_ERR, 'Could not make new Facebook client obj!',
            __FILE__);
    }

    return $facebook;
}

function updateProfileBox($facebook, $flink, $notice) {
    $fbaction = new FacebookAction($output='php://output', $indent=true, $facebook, $flink);
    $fbaction->updateProfileBox($notice);
}

function isFacebookBound($notice, $flink) {

    // If the user does not want to broadcast to Facebook, move along
    if (!($flink->noticesync & FOREIGN_NOTICE_SEND == FOREIGN_NOTICE_SEND)) {
        common_log(LOG_INFO, "Skipping notice $notice->id " .
            'because user has FOREIGN_NOTICE_SEND bit off.');
        return false;
    }

    $success = false;

    // If it's not a reply, or if the user WANTS to send @-replies...
    if (!preg_match('/@[a-zA-Z0-9_]{1,15}\b/u', $notice->content) ||
        ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY)) {

        $success = true;

        // The two condition below are deal breakers:

        // Avoid a loop
        if ($notice->source == 'Facebook') {
            common_log(LOG_INFO, "Skipping notice $notice->id because its " .
                'source is Facebook.');
            $success = false;
        }

        $facebook = getFacebook();
        $fbuid = $flink->foreign_id;

        try {

            // Check to see if the user has given the FB app status update perms
            $result = $facebook->api_client->
                users_hasAppPermission('status_update', $fbuid);

            if ($result != 1) {
                $user = $flink->getUser();
                $msg = "Can't send notice $notice->id to Facebook " .
                    "because user $user->nickname hasn't given the " .
                    'Facebook app \'status_update\' permission.';
                common_log(LOG_INFO, $msg);
                $success = false;
            }

        } catch(FacebookRestClientException $e){
            common_log(LOG_ERR, $e->getMessage());
            $success = false;
        }

    }

    return $success;

}

function facebookBroadcastNotice($notice)
{
    $facebook = getFacebook();
    $flink = Foreign_link::getByUserID($notice->profile_id, FACEBOOK_SERVICE);
    $fbuid = $flink->foreign_id;

    if (isFacebookBound($notice, $flink)) {

        $status = null;

        // Get the status 'verb' (prefix) the user has set
        try {
            $prefix = $facebook->api_client->
                data_getUserPreference(FACEBOOK_NOTICE_PREFIX, $fbuid);

            $status = "$prefix $notice->content";

        } catch(FacebookRestClientException $e) {
            common_log(LOG_ERR, $e->getMessage());
            return false;
        }

        // Okay, we're good to go!

        try {
            $facebook->api_client->users_setStatus($status, $fbuid, false, true);
            updateProfileBox($facebook, $flink, $notice);
        } catch(FacebookRestClientException $e) {
            common_log(LOG_ERR, $e->getMessage());
            return false;

             // Should we remove flink if this fails?
        }

    }

    return true;
}
