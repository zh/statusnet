<?php
/**
 * Handler for remote subscription finish callback
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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
 **/

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/extlib/libomb/service_consumer.php';
require_once INSTALLDIR.'/lib/omb.php';

/**
 * Handler for remote subscription finish callback
 *
 * When a remote user subscribes a local user, a redirect to this action is
 * issued after the remote user authorized his service to subscribe.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class FinishremotesubscribeAction extends Action
{

    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return nothing
     *
     **/
    function handle($args)
    {
        parent::handle($args);

        /* Restore session data. RemotesubscribeAction should have stored
           this entry. */
        $service  = unserialize($_SESSION['oauth_authorization_request']);

        if (!$service) {
            $this->clientError(_('Not expecting this response!'));
            return;
        }

        common_debug('stored request: '. print_r($service, true), __FILE__);

        /* Create user objects for both users. Do it early for request
           validation. */
        $user = User::staticGet('uri', $service->getListeneeURI());

        if (!$user) {
            $this->clientError(_('User being listened to does not exist.'));
            return;
        }

        $other = User::staticGet('uri', $service->getListenerURI());

        if ($other) {
            $this->clientError(_('You can use the local subscription!'));
            return;
        }

        $remote = Remote_profile::staticGet('uri', $service->getListenerURI());

        $profile = Profile::staticGet($remote->id);

        if ($user->hasBlocked($profile)) {
            $this->clientError(_('That user has blocked you from subscribing.'));
            return;
        }

        /* Perform the handling itself via libomb. */
        try {
            $service->finishAuthorization();
        } catch (OAuthException $e) {
            if ($e->getMessage() == 'The authorized token does not equal the ' .
                                    'submitted token.') {
                $this->clientError(_('You are not authorized.'));
                return;
            } else {
                $this->clientError(_('Could not convert request token to ' .
                                     'access token.'));
                return;
            }
        } catch (OMB_RemoteServiceException $e) {
            $this->clientError(_('Remote service uses unknown version of ' .
                                 'OMB protocol.'));
            return;
        } catch (Exception $e) {
            common_debug('Got exception ' . print_r($e, true), __FILE__);
            $this->clientError($e->getMessage());
            return;
        }

        /* The service URLs are not accessible from datastore, so setting them
           after insertion of the profile. */
        $orig_remote = clone($remote);

        $remote->postnoticeurl    =
                            $service->getServiceURI(OMB_ENDPOINT_POSTNOTICE);
        $remote->updateprofileurl =
                            $service->getServiceURI(OMB_ENDPOINT_UPDATEPROFILE);

        if (!$remote->update($orig_remote)) {
                $this->serverError(_('Error updating remote profile'));
                return;
        }

        /* Clear the session data. */
        unset($_SESSION['oauth_authorization_request']);

        /* If we show subscriptions in reverse chronological order, the new one
           should show up close to the top of the page. */
        common_redirect(common_local_url('subscribers', array('nickname' =>
                                                             $user->nickname)),
                        303);
    }

    function add_avatar($profile, $url)
    {
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        copy($url, $temp_filename);
        $imagefile = new ImageFile($profile->id, $temp_filename);
        $filename = Avatar::filename($profile->id,
                                     image_type_to_extension($imagefile->type),
                                     null,
                                     common_timestamp());
        rename($temp_filename, Avatar::path($filename));
        return $profile->setOriginal($filename);
    }

    function access_token($omb)
    {

        common_debug('starting request for access token', __FILE__);

        $con = omb_oauth_consumer();
        $tok = new OAuthToken($omb['token'], $omb['secret']);

        common_debug('using request token "'.$tok.'"', __FILE__);

        $url = $omb['access_token_url'];

        common_debug('using access token url "'.$url.'"', __FILE__);

        # XXX: Is this the right thing to do? Strip off GET params and make them
        # POST params? Seems wrong to me.

        $parsed = parse_url($url);
        $params = array();
        parse_str($parsed['query'], $params);

        $req = OAuthRequest::from_consumer_and_token($con, $tok, "POST", $url, $params);

        $req->set_parameter('omb_version', OMB_VERSION_01);

        # XXX: test to see if endpoint accepts this signature method

        $req->sign_request(omb_hmac_sha1(), $con, $tok);

        # We re-use this tool's fetcher, since it's pretty good

        common_debug('posting to access token url "'.$req->get_normalized_http_url().'"', __FILE__);
        common_debug('posting request data "'.$req->to_postdata().'"', __FILE__);

        $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
        $result = $fetcher->post($req->get_normalized_http_url(),
                                 $req->to_postdata(),
                                 array('User-Agent: StatusNet/' . STATUSNET_VERSION));

        common_debug('got result: "'.print_r($result,true).'"', __FILE__);

        if ($result->status != 200) {
            return null;
        }

        parse_str($result->body, $return);

        return array($return['oauth_token'], $return['oauth_token_secret']);
    }
}
