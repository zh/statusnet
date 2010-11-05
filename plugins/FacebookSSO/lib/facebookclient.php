<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for communicating with Facebook
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class for communication with Facebook
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Facebookclient
{
    protected $facebook      = null; // Facebook Graph client obj
    protected $flink         = null; // Foreign_link StatusNet -> Facebook
    protected $notice        = null; // The user's notice
    protected $user          = null; // Sender of the notice
    protected $oldRestClient = null; // Old REST API client

    function __constructor($notice)
    {
        $this->facebook = self::getFacebook();
        $this->notice   = $notice;

        $this->flink = Foreign_link::getByUserID(
            $notice->profile_id,
            FACEBOOK_SERVICE
        );
        
        $this->user = $this->flink->getUser();

        $this->oldRestClient = self::getOldRestClient();
    }

    /*
     * Get and instance of the old REST API client for sending notices from
     * users with Facebook links that pre-exist the Graph API
     */
    static function getOldRestClient()
    {
        $apikey = common_config('facebook', 'apikey');
        $secret = common_config('facebook', 'secret');

        // If there's no app key and secret set in the local config, look
        // for a global one
        if (empty($apikey) || empty($secret)) {
            $apikey = common_config('facebook', 'global_apikey');
            $secret = common_config('facebook', 'global_secret');
        }

        return new FacebookRestClient($apikey, $secret, null);
    }

    /*
     * Get an instance of the Facebook Graph SDK object
     *
     * @param string $appId     Application
     * @param string $secret    Facebook API secret
     *
     * @return Facebook A Facebook SDK obj
     */
    static function getFacebook($appId = null, $secret = null)
    {
        // Check defaults and configuration for application ID and secret
        if (empty($appId)) {
            $appId = common_config('facebook', 'appid');
        }

        if (empty($secret)) {
            $secret = common_config('facebook', 'secret');
        }

        // If there's no app ID and secret set in the local config, look
        // for a global one
        if (empty($appId) || empty($secret)) {
            $appId  = common_config('facebook', 'global_appid');
            $secret = common_config('facebook', 'global_secret');
        }

        return new Facebook(
            array(
               'appId'  => $appId,
               'secret' => $secret,
               'cookie' => true
            )
        );
    }

    /*
     * Broadcast a notice to Facebook
     *
     * @param Notice $notice    the notice to send
     */
    static function facebookBroadcastNotice($notice)
    {
        $client = new Facebookclient($notice);
        $client->sendNotice();
    }

    /*
     * Should the notice go to Facebook?
     */
    function isFacebookBound() {

        if (empty($this->flink)) {
            common_log(
                LOG_WARN,
                sprintf(
                    "No Foreign_link to Facebook for the author of notice %d.",
                    $this->notice->id
                ),
                __FILE__
            );
            return false;
        }

        // Avoid a loop
        if ($this->notice->source == 'Facebook') {
            common_log(
                LOG_INFO,
                sprintf(
                    'Skipping notice %d because its source is Facebook.',
                    $this->notice->id
                ),
                __FILE__
            );
            return false;
        }

        // If the user does not want to broadcast to Facebook, move along
        if (!($this->flink->noticesync & FOREIGN_NOTICE_SEND == FOREIGN_NOTICE_SEND)) {
            common_log(
                LOG_INFO,
                sprintf(
                    'Skipping notice %d because user has FOREIGN_NOTICE_SEND bit off.',
                    $this->notice->id
                ),
                __FILE__
            );
            return false;
        }

        // If it's not a reply, or if the user WANTS to send @-replies,
        // then, yeah, it can go to Facebook.
        if (!preg_match('/@[a-zA-Z0-9_]{1,15}\b/u', $this->notice->content) ||
            ($this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY)) {
            return true;
        }

        return false;
    }

    /*
     * Determine whether we should send this notice using the Graph API or the
     * old REST API and then dispatch
     */
    function sendNotice()
    {
        // If there's nothing in the credentials field try to send via
        // the Old Rest API

        if (empty($this->flink->credentials)) {
            $this->sendOldRest();
        } else {

            // Otherwise we most likely have an access token
            $this->sendGraph();
        }
    }

    /*
     * Send a notice to Facebook using the Graph API
     */
    function sendGraph()
    {
        common_debug("Send notice via Graph API", __FILE__);
    }

    /*
     * Send a notice to Facebook using the deprecated Old REST API. We need this
     * for backwards compatibility. Users who signed up for Facebook bridging
     * using the old Facebook Canvas application do not have an OAuth 2.0
     * access token.
     */
    function sendOldRest()
    {
        if (isFacebookBound()) {

            try {

                $canPublish = $this->checkPermission('publish_stream');
                $canUpdate  = $this->checkPermission('status_update');

                // Post to Facebook
                if ($notice->hasAttachments() && $canPublish == 1) {
                    $this->restPublishStream();
                } elseif ($canUpdate == 1 || $canPublish == 1) {
                    $this->restStatusUpdate();
                } else {

                    $msg = 'Not sending notice %d to Facebook because user %s '
                         . '(%d), fbuid %s,  does not have \'status_update\' '
                         . 'or \'publish_stream\' permission.';

                    common_log(
                        LOG_WARNING,
                        sprintf(
                            $msg,
                            $this->notice->id,
                            $this->user->nickname,
                            $this->user->id,
                            $this->flink->foreign_id
                        ),
                        __FILE__
                    );
                }

            } catch (FacebookRestClientException $e) {
                return $this->handleFacebookError($e);
            }
        }

        return true;
    }

    /*
     * Query Facebook to to see if a user has permission
     *
     *
     *
     * @param $permission the permission to check for - must be either
     *                    public_stream or status_update
     *
     * @return boolean result
     */
    function checkPermission($permission)
    {

        if (!in_array($permission, array('publish_stream', 'status_update'))) {
             throw new ServerExpception("No such permission!");
        }

        $fbuid = $this->flink->foreign_link;

        common_debug(
            sprintf(
                'Checking for %s permission for user %s (%d), fbuid %s',
                $permission,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        // NOTE: $this->oldRestClient->users_hasAppPermission() has been
        // returning bogus results, so we're using FQL to check for
        // permissions

        $fql = sprintf(
            "SELECT %s FROM permissions WHERE uid = %s",
            $permission,
            $fbuid
        );

        $result = $this->oldRestClient->fql_query($fql);

        $hasPermission = 0;

        if (isset($result[0][$permission])) {
            $canPublish = $result[0][$permission];
        }

        if ($hasPermission == 1) {

            common_debug(
                sprintf(
                    '%s (%d), fbuid %s has %s permission',
                    $permission,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );

            return true;

        } else {

            $logMsg = '%s (%d), fbuid $fbuid does NOT have %s permission.'
                    . 'Facebook returned: %s';

            common_debug(
                sprintf(
                    $logMsg,
                    $this->user->nickname,
                    $this->user->id,
                    $permission,
                    $fbuid,
                    var_export($result, true)
                ),
                __FILE__
            );

            return false;

        }
    
    }

    function handleFacebookError($e)
    {
        $fbuid  = $this->flink->foreign_id;
        $code   = $e->getCode();
        $errmsg = $e->getMessage();

        // XXX: Check for any others?
        switch($code) {
         case 100: // Invalid parameter
            $msg = 'Facebook claims notice %d was posted with an invalid '
                 . 'parameter (error code 100 - %s) Notice details: '
                 . '[nickname=%s, user id=%d, fbuid=%d, content="%s"]. '
                 . 'Dequeing.';
            common_log(
                LOG_ERR, sprintf(
                    $msg,
                    $this->notice->id,
                    $errmsg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid,
                    $this->notice->content
                ),
                __FILE__
            );
            return true;
            break;
         case 200: // Permissions error
         case 250: // Updating status requires the extended permission status_update
            $this->disconnect();
            return true; // dequeue
            break;
         case 341: // Feed action request limit reached
                $msg = '%s (userid=%d, fbuid=%d) has exceeded his/her limit '
                     . 'for posting notices to Facebook today. Dequeuing '
                     . 'notice %d';
                common_log(
                    LOG_INFO, sprintf(
                        $msg,
                        $user->nickname,
                        $user->id,
                        $fbuid,
                        $this->notice->id
                    ),
                    __FILE__
                );
            // @fixme: We want to rety at a later time when the throttling has expired
            // instead of just giving up.
            return true;
            break;
         default:
            $msg = 'Facebook returned an error we don\'t know how to deal with '
                 . 'when posting notice %d. Error code: %d, error message: "%s"'
                 . ' Notice details: [nickname=%s, user id=%d, fbuid=%d, '
                 . 'notice content="%s"]. Dequeing.';
            common_log(
                LOG_ERR, sprintf(
                    $msg,
                    $this->notice->id,
                    $code,
                    $errmsg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid,
                    $this->notice->content
                ),
                __FILE__
            );
            return true; // dequeue
            break;
        }
    }

    function restStatusUpdate()
    {
        $fbuid = $this->flink->foreign_id;

        common_debug(
            sprintf(
                "Attempting to post notice %d as a status update for %s (%d), fbuid %s",
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $result = $this->oldRestClient->users_setStatus(
             $this->notice->content,
             $fbuid,
             false,
             true
        );

        common_log(
            LOG_INFO,
            sprintf(
                "Posted notice %s as a status update for %s (%d), fbuid %s",
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );
    }

    function restPublishStream()
    {
        $fbuid = $this->flink->foreign_id;

        common_debug(
            sprintf(
                'Attempting to post notice %d as stream item with attachment for '
                . '%s (%d) fbuid %s',
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $fbattachment = format_attachments($notice->attachments());

        $this->oldRestClient->stream_publish(
            $this->notice->content,
            $fbattachment,
            null,
            null,
            $fbuid
        );

        common_log(
            LOG_INFO,
            sprintf(
                'Posted notice %d as a stream item with attachment for %s '
                . '(%d), fbuid %s',
                $this->notice->id,
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );
        
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

    function disconnect()
    {
        $fbuid = $this->flink->foreign_link;

        common_log(
            LOG_INFO,
            sprintf(
                'Removing Facebook link for %s (%d), fbuid %s',
                $this->user->nickname,
                $this->user->id,
                $fbuid
            ),
            __FILE__
        );

        $result = $flink->delete();

        if (empty($result)) {
            common_log(
                LOG_ERR,
                sprintf(
                    'Could not remove Facebook link for %s (%d), fbuid %s',
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );
            common_log_db_error($flink, 'DELETE', __FILE__);
        }

        // Notify the user that we are removing their Facebook link

        $result = $this->mailFacebookDisconnect();

        if (!$result) {

            $msg = 'Unable to send email to notify %s (%d), fbuid %s '
                 . 'about his/her Facebook link being removed.';

            common_log(
                LOG_WARNING,
                sprintf(
                    $msg,
                    $this->user->nickname,
                    $this->user->id,
                    $fbuid
                ),
                __FILE__
            );
        }
    }

    /**
     * Send a mail message to notify a user that her Facebook link
     * has been terminated.
     *
     * @return boolean success flag
     */
    function mailFacebookDisconnect()
    {
        $profile = $user->getProfile();

        $siteName = common_config('site', 'name');

        common_switch_locale($user->language);

        $subject = sprintf(
            _m('Your Facebook connection has been removed'),
            $siteName
        );

        $msg = <<<BODY
Hi, %1$s. We're sorry to inform you we are unable to publish your notice to
Facebook, and have removed the connection between your %2$s account and Facebook.

This may have happened because you have removed permission for %2$s to post on
your behalf, or perhaps you have deactivated your Facebook account. You can
reconnect your %s account to Facebook at any time by logging in with Facebook
again.
BODY;
        $body = sprintf(
            _m($msg),
            $this->user->nickname,
            $siteName
        );
        
        common_switch_locale();

        return mail_to_user($this->user, $subject, $body);
    }

}
