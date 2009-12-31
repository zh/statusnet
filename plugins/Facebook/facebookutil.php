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

require_once INSTALLDIR . '/plugins/Facebook/facebook/facebook.php';
require_once INSTALLDIR . '/plugins/Facebook/facebookaction.php';
require_once INSTALLDIR . '/lib/noticelist.php';

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

    if (empty($facebook)) {
        common_log(LOG_ERR, 'Could not make new Facebook client obj!',
            __FILE__);
    }

    return $facebook;
}

function isFacebookBound($notice, $flink) {

    if (empty($flink)) {
        return false;
    }

    // Avoid a loop

    if ($notice->source == 'Facebook') {
        common_log(LOG_INFO, "Skipping notice $notice->id because its " .
                   'source is Facebook.');
        return false;
    }

    // If the user does not want to broadcast to Facebook, move along

    if (!($flink->noticesync & FOREIGN_NOTICE_SEND == FOREIGN_NOTICE_SEND)) {
        common_log(LOG_INFO, "Skipping notice $notice->id " .
            'because user has FOREIGN_NOTICE_SEND bit off.');
        return false;
    }

    // If it's not a reply, or if the user WANTS to send @-replies,
    // then, yeah, it can go to Facebook.

    if (!preg_match('/@[a-zA-Z0-9_]{1,15}\b/u', $notice->content) ||
        ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY)) {
        return true;
    }

    return false;

}

function facebookBroadcastNotice($notice)
{
    $facebook = getFacebook();
    $flink = Foreign_link::getByUserID($notice->profile_id, FACEBOOK_SERVICE);

    if (isFacebookBound($notice, $flink)) {

        // Okay, we're good to go, update the FB status

        $status = null;
        $fbuid = $flink->foreign_id;
        $user = $flink->getUser();
        $attachments  = $notice->attachments();

        try {

            // Get the status 'verb' (prefix) the user has set

            // XXX: Does this call count against our per user FB request limit?
            // If so we should consider storing verb elsewhere or not storing

            $prefix = trim($facebook->api_client->data_getUserPreference(FACEBOOK_NOTICE_PREFIX,
                                                                         $fbuid));

            $status = "$prefix $notice->content";

            $can_publish = $facebook->api_client->users_hasAppPermission('publish_stream',
                                                                         $fbuid);

            $can_update  = $facebook->api_client->users_hasAppPermission('status_update',
                                                                         $fbuid);
            if (!empty($attachments) && $can_publish == 1) {
                $fbattachment = format_attachments($attachments);
                $facebook->api_client->stream_publish($status, $fbattachment,
                                                      null, null, $fbuid);
                common_log(LOG_INFO,
                           "Posted notice $notice->id w/attachment " .
                           "to Facebook user's stream (fbuid = $fbuid).");
            } elseif ($can_update == 1 || $can_publish == 1) {
                $facebook->api_client->users_setStatus($status, $fbuid, false, true);
                common_log(LOG_INFO,
                           "Posted notice $notice->id to Facebook " .
                           "as a status update (fbuid = $fbuid).");
            } else {
                $msg = "Not sending notice $notice->id to Facebook " .
                  "because user $user->nickname hasn't given the " .
                  'Facebook app \'status_update\' or \'publish_stream\' permission.';
                common_log(LOG_WARNING, $msg);
            }

            // Finally, attempt to update the user's profile box

            if ($can_publish == 1 || $can_update == 1) {
                updateProfileBox($facebook, $flink, $notice);
            }

        } catch (FacebookRestClientException $e) {

            $code = $e->getCode();

            $msg = "Facebook returned error code $code: " .
              $e->getMessage() . ' - ' .
              "Unable to update Facebook status (notice $notice->id) " .
              "for $user->nickname (user id: $user->id)!";

            common_log(LOG_WARNING, $msg);

            if ($code == 100 || $code == 200 || $code == 250) {

                // 100 The account is 'inactive' (probably - this is not well documented)
                // 200 The application does not have permission to operate on the passed in uid parameter.
                // 250 Updating status requires the extended permission status_update or publish_stream.
                // see: http://wiki.developers.facebook.com/index.php/Users.setStatus#Example_Return_XML

                remove_facebook_app($flink);

        } else {

                // Try sending again later.

                return false;
            }

        }
    }

    return true;

}

function updateProfileBox($facebook, $flink, $notice) {
    $fbaction = new FacebookAction($output = 'php://output',
                                   $indent = null, $facebook, $flink);
    $fbaction->updateProfileBox($notice);
}

function format_attachments($attachments)
{
    $fbattachment          = array();
    $fbattachment['media'] = array();

    foreach($attachments as $attachment)
    {
        if($enclosure = $attachment->getEnclosure()){
            $fbmedia = get_fbmedia_for_attachment($enclosure);
        }else{
            $fbmedia = get_fbmedia_for_attachment($attachment);
        }
        if($fbmedia){
            $fbattachment['media'][]=$fbmedia;
        }else{
            $fbattachment['name'] = ($attachment->title ?
                                  $attachment->title : $attachment->url);
            $fbattachment['href'] = $attachment->url;
        }
    }
    if(count($fbattachment['media'])>0){
        unset($fbattachment['name']);
        unset($fbattachment['href']);
    }
    return $fbattachment;
}

/**
* given an File objects, returns an associative array suitable for Facebook media
*/
function get_fbmedia_for_attachment($attachment)
{
    $fbmedia    = array();

    if (strncmp($attachment->mimetype, 'image/', strlen('image/')) == 0) {
        $fbmedia['type']         = 'image';
        $fbmedia['src']          = $attachment->url;
        $fbmedia['href']         = $attachment->url;
    } else if ($attachment->mimetype == 'audio/mpeg') {
        $fbmedia['type']         = 'mp3';
        $fbmedia['src']          = $attachment->url;
    }else if ($attachment->mimetype == 'application/x-shockwave-flash') {
        $fbmedia['type']         = 'flash';

        // http://wiki.developers.facebook.com/index.php/Attachment_%28Streams%29
        // says that imgsrc is required... but we have no value to put in it
        // $fbmedia['imgsrc']='';

        $fbmedia['swfsrc']       = $attachment->url;
    }else{
        return false;
    }
    return $fbmedia;
}

function remove_facebook_app($flink)
{

    $user = $flink->getUser();

    common_log(LOG_INFO, 'Removing Facebook App Foreign link for ' .
        "user $user->nickname (user id: $user->id).");

    $result = $flink->delete();

    if (empty($result)) {
        common_log(LOG_ERR, 'Could not remove Facebook App ' .
            "Foreign_link for $user->nickname (user id: $user->id)!");
        common_log_db_error($flink, 'DELETE', __FILE__);
    }

    // Notify the user that we are removing their FB app access

    $result = mail_facebook_app_removed($user);

    if (!$result) {

        $msg = 'Unable to send email to notify ' .
            "$user->nickname (user id: $user->id) " .
            'that their Facebook app link was ' .
            'removed!';

        common_log(LOG_WARNING, $msg);
    }

}

/**
 * Send a mail message to notify a user that her Facebook Application
 * access has been removed.
 *
 * @param User $user   user whose Facebook app link has been removed
 *
 * @return boolean success flag
 */

function mail_facebook_app_removed($user)
{
    common_init_locale($user->language);

    $profile = $user->getProfile();

    $site_name = common_config('site', 'name');

    $subject = sprintf(
        _m('Your %1$s Facebook application access has been disabled.',
            $site_name));

    $body = sprintf(_m("Hi, %1\$s. We're sorry to inform you that we are " .
        'unable to update your Facebook status from %2$s, and have disabled ' .
        'the Facebook application for your account. This may be because ' .
        'you have removed the Facebook application\'s authorization, or ' .
        'have deleted your Facebook account.  You can re-enable the ' .
        'Facebook application and automatic status updating by ' .
        "re-installing the %2\$s Facebook application.\n\nRegards,\n\n%2\$s"),
        $user->nickname, $site_name);

    common_init_locale();
    return mail_to_user($user, $subject, $body);

}
