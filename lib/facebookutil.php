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

    if (empty($flink)) {
        return false;
    }

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
                users_hasAppPermission('publish_stream', $fbuid);

            if ($result != 1) {
                $result = $facebook->api_client->
                    users_hasAppPermission('status_update', $fbuid);
            }
            if ($result != 1) {
                $user = $flink->getUser();
                $msg = "Not sending notice $notice->id to Facebook " .
                    "because user $user->nickname hasn't given the " .
                    'Facebook app \'status_update\' or \'publish_stream\' permission.';
                common_debug($msg);
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

    if (isFacebookBound($notice, $flink)) {

        $status = null;
        $fbuid = $flink->foreign_id;

        $user = $flink->getUser();

        // Get the status 'verb' (prefix) the user has set

        try {
            $prefix = $facebook->api_client->
                data_getUserPreference(FACEBOOK_NOTICE_PREFIX, $fbuid);

            $status = "$prefix $notice->content";

        } catch(FacebookRestClientException $e) {
            common_log(LOG_WARNING, $e->getMessage());
            common_log(LOG_WARNING,
                'Unable to get the status verb setting from Facebook ' .
                "for $user->nickname (user id: $user->id).");
        }

        // Okay, we're good to go, update the FB status

        try {
            $result = $facebook->api_client->
                users_hasAppPermission('publish_stream', $fbuid);
            if($result == 1){
                // authorized to use the stream api, so use it
                $fbattachment = null;
                $attachments = $notice->attachments();
                if($attachments){
                    $fbattachment=array();
                    $fbattachment['media']=array();
                    //facebook only supports one attachment per item
                    $attachment = $attachments[0];
                    $fbmedia=array();
                    if(strncmp($attachment->mimetype,'image/',strlen('image/'))==0){
                        $fbmedia['type']='image';
                        $fbmedia['src']=$attachment->url;
                        $fbmedia['href']=$attachment->url;
                        $fbattachment['media'][]=$fbmedia;
/* Video doesn't seem to work. The notice never makes it to facebook, and no error is reported.
                    }else if(strncmp($attachment->mimetype,'video/',strlen('image/'))==0 || $attachment->mimetype="application/ogg"){
                        $fbmedia['type']='video';
                        $fbmedia['video_src']=$attachment->url;
                        // http://wiki.developers.facebook.com/index.php/Attachment_%28Streams%29
                        // says that preview_img is required... but we have no value to put in it
                        // $fbmedia['preview_img']=$attachment->url;
                        if($attachment->title){
                            $fbmedia['video_title']=$attachment->title;
                        }
                        $fbmedia['video_type']=$attachment->mimetype;
                        $fbattachment['media'][]=$fbmedia;
*/
                    }else if($attachment->mimetype=='audio/mpeg'){
                        $fbmedia['type']='mp3';
                        $fbmedia['src']=$attachment->url;
                        $fbattachment['media'][]=$fbmedia;
                    }else if($attachment->mimetype=='application/x-shockwave-flash'){
                        $fbmedia['type']='flash';
                        // http://wiki.developers.facebook.com/index.php/Attachment_%28Streams%29
                        // says that imgsrc is required... but we have no value to put in it
                        // $fbmedia['imgsrc']='';
                        $fbmedia['swfsrc']=$attachment->url;
                        $fbattachment['media'][]=$fbmedia;
                    }else{
                        $fbattachment['name']=($attachment->title?$attachment->title:$attachment->url);
                        $fbattachment['href']=$attachment->url;
                    }
                }
                $facebook->api_client->stream_publish($status, $fbattachment, null, null, $fbuid);
            }else{
                $facebook->api_client->users_setStatus($status, $fbuid, false, true);
            }
        } catch(FacebookRestClientException $e) {
            common_log(LOG_ERR, $e->getMessage());
            common_log(LOG_ERR,
                'Unable to update Facebook status for ' .
                "$user->nickname (user id: $user->id)!");

            $code = $e->getCode();

            if ($code >= 200) {

                // 200 The application does not have permission to operate on the passed in uid parameter.
                // 250 Updating status requires the extended permission status_update or publish_stream.
                // see: http://wiki.developers.facebook.com/index.php/Users.setStatus#Example_Return_XML

                remove_facebook_app($flink);
            }

        }

        // Now try to update the profile box

        try {
            updateProfileBox($facebook, $flink, $notice);
        } catch(FacebookRestClientException $e) {
            common_log(LOG_WARNING, $e->getMessage());
            common_log(LOG_WARNING,
                'Unable to update Facebook profile box for ' .
                "$user->nickname (user id: $user->id).");
        }

    }

    return true;
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
