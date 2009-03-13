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

require_once(INSTALLDIR.'/lib/omb.php');
define('TIMESTAMP_THRESHOLD', 300);

class UserauthorizationAction extends Action
{
    var $error;
    var $req;

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            # CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $req = $this->getStoredRequest();
                $this->showForm($req, _('There was a problem with your session token. '.
                                        'Try again, please.'));
                return;
            }
            # We've shown the form, now post user's choice
            $this->sendAuthorization();
        } else {
            if (!common_logged_in()) {
                # Go log in, and then come back
                common_set_returnto($_SERVER['REQUEST_URI']);

                common_redirect(common_local_url('login'));
                return;
            }
            try {
                # this must be a new request
                $req = $this->getNewRequest();
                if (!$req) {
                    $this->clientError(_('No request found!'));
                }
                # XXX: only validate new requests, since nonce is one-time use
                $this->validateRequest($req);
                $this->storeRequest($req);
                $this->showForm($req);
            } catch (OAuthException $e) {
                $this->clearRequest();
                $this->clientError($e->getMessage());
                return;
            }

        }
    }

    function showForm($req, $error=null)
    {
        $this->req = $req;
        $this->error = $error;
        $this->showPage();
    }

    function title()
    {
        return _('Authorize subscription');
    }

    function showPageNotice()
    {
        $this->element('p', null, _('Please check these details to make sure '.
                                    'that you want to subscribe to this user\'s notices. '.
                                    'If you didn\'t just ask to subscribe to someone\'s notices, '.
                                    'click "Reject".'));
    }

    function showContent()
    {
        $req = $this->req;

        $nickname = $req->get_parameter('omb_listenee_nickname');
        $profile = $req->get_parameter('omb_listenee_profile');
        $license = $req->get_parameter('omb_listenee_license');
        $fullname = $req->get_parameter('omb_listenee_fullname');
        $homepage = $req->get_parameter('omb_listenee_homepage');
        $bio = $req->get_parameter('omb_listenee_bio');
        $location = $req->get_parameter('omb_listenee_location');
        $avatar = $req->get_parameter('omb_listenee_avatar');

        $this->elementStart('div', 'profile');
        if ($avatar) {
            $this->element('img', array('src' => $avatar,
                                        'class' => 'avatar',
                                        'width' => AVATAR_PROFILE_SIZE,
                                        'height' => AVATAR_PROFILE_SIZE,
                                        'alt' => $nickname));
        }
        $this->element('a', array('href' => $profile,
                                  'class' => 'external profile nickname'),
                       $nickname);
        if (!is_null($fullname)) {
            $this->elementStart('div', 'fullname');
            if (!is_null($homepage)) {
                $this->element('a', array('href' => $homepage),
                               $fullname);
            } else {
                $this->text($fullname);
            }
            $this->elementEnd('div');
        }
        if (!is_null($location)) {
            $this->element('div', 'location', $location);
        }
        if (!is_null($bio)) {
            $this->element('div', 'bio', $bio);
        }
        $this->elementStart('div', 'license');
        $this->element('a', array('href' => $license,
                                  'class' => 'license'),
                       $license);
        $this->elementEnd('div');
        $this->elementEnd('div');
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'userauthorization',
                                          'name' => 'userauthorization',
                                          'action' => common_local_url('userauthorization')));
        $this->hidden('token', common_session_token());
        $this->submit('accept', _('Accept'));
        $this->submit('reject', _('Reject'));
        $this->elementEnd('form');
    }

    function sendAuthorization()
    {
        $req = $this->getStoredRequest();

        if (!$req) {
            $this->clientError(_('No authorization request!'));
            return;
        }

        $callback = $req->get_parameter('oauth_callback');

        if ($this->arg('accept')) {
            if (!$this->authorizeToken($req)) {
                $this->clientError(_('Error authorizing token'));
            }
            if (!$this->saveRemoteProfile($req)) {
                $this->clientError(_('Error saving remote profile'));
            }
            if (!$callback) {
                $this->showAcceptMessage($req->get_parameter('oauth_token'));
            } else {
                $params = array();
                $params['oauth_token'] = $req->get_parameter('oauth_token');
                $params['omb_version'] = OMB_VERSION_01;
                $user = User::staticGet('uri', $req->get_parameter('omb_listener'));
                $profile = $user->getProfile();
                if (!$profile) {
                    common_log_db_error($user, 'SELECT', __FILE__);
                    $this->serverError(_('User without matching profile'));
                    return;
                }
                $params['omb_listener_nickname'] = $user->nickname;
                $params['omb_listener_profile'] = common_local_url('showstream',
                                                                   array('nickname' => $user->nickname));
                if (!is_null($profile->fullname)) {
                    $params['omb_listener_fullname'] = $profile->fullname;
                }
                if (!is_null($profile->homepage)) {
                    $params['omb_listener_homepage'] = $profile->homepage;
                }
                if (!is_null($profile->bio)) {
                    $params['omb_listener_bio'] = $profile->bio;
                }
                if (!is_null($profile->location)) {
                    $params['omb_listener_location'] = $profile->location;
                }
                $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
                if ($avatar) {
                    $params['omb_listener_avatar'] = $avatar->url;
                }
                $parts = array();
                foreach ($params as $k => $v) {
                    $parts[] = $k . '=' . OAuthUtil::urlencode_rfc3986($v);
                }
                $query_string = implode('&', $parts);
                $parsed = parse_url($callback);
                $url = $callback . (($parsed['query']) ? '&' : '?') . $query_string;
                common_redirect($url, 303);
            }
        } else {
            if (!$callback) {
                $this->showRejectMessage();
            } else {
                # XXX: not 100% sure how to signal failure... just redirect without token?
                common_redirect($callback, 303);
            }
        }
    }

    function authorizeToken(&$req)
    {
        $consumer_key = $req->get_parameter('oauth_consumer_key');
        $token_field = $req->get_parameter('oauth_token');
        $rt = new Token();
        $rt->consumer_key = $consumer_key;
        $rt->tok = $token_field;
        $rt->type = 0;
        $rt->state = 0;
        if ($rt->find(true)) {
            $orig_rt = clone($rt);
            $rt->state = 1; # Authorized but not used
            if ($rt->update($orig_rt)) {
                return true;
            }
        }
        return false;
    }

    # XXX: refactor with similar code in finishremotesubscribe.php

    function saveRemoteProfile(&$req)
    {
        # FIXME: we should really do this when the consumer comes
        # back for an access token. If they never do, we've got stuff in a
        # weird state.

        $nickname = $req->get_parameter('omb_listenee_nickname');
        $fullname = $req->get_parameter('omb_listenee_fullname');
        $profile_url = $req->get_parameter('omb_listenee_profile');
        $homepage = $req->get_parameter('omb_listenee_homepage');
        $bio = $req->get_parameter('omb_listenee_bio');
        $location = $req->get_parameter('omb_listenee_location');
        $avatar_url = $req->get_parameter('omb_listenee_avatar');

        $listenee = $req->get_parameter('omb_listenee');
        $remote = Remote_profile::staticGet('uri', $listenee);

        if ($remote) {
            $exists = true;
            $profile = Profile::staticGet($remote->id);
            $orig_remote = clone($remote);
            $orig_profile = clone($profile);
        } else {
            $exists = false;
            $remote = new Remote_profile();
            $remote->uri = $listenee;
            $profile = new Profile();
        }

        $profile->nickname = $nickname;
        $profile->profileurl = $profile_url;

        if (!is_null($fullname)) {
            $profile->fullname = $fullname;
        }
        if (!is_null($homepage)) {
            $profile->homepage = $homepage;
        }
        if (!is_null($bio)) {
            $profile->bio = $bio;
        }
        if (!is_null($location)) {
            $profile->location = $location;
        }

        if ($exists) {
            $profile->update($orig_profile);
        } else {
            $profile->created = DB_DataObject_Cast::dateTime(); # current time
            $id = $profile->insert();
            if (!$id) {
                return false;
            }
            $remote->id = $id;
        }

        if ($exists) {
            if (!$remote->update($orig_remote)) {
                return false;
            }
        } else {
            $remote->created = DB_DataObject_Cast::dateTime(); # current time
            if (!$remote->insert()) {
                return false;
            }
        }

        if ($avatar_url) {
            if (!$this->addAvatar($profile, $avatar_url)) {
                return false;
            }
        }

        $user = common_current_user();
        $datastore = omb_oauth_datastore();
        $consumer = $this->getConsumer($datastore, $req);
        $token = $this->getToken($datastore, $req, $consumer);

        $sub = new Subscription();
        $sub->subscriber = $user->id;
        $sub->subscribed = $remote->id;
        $sub->token = $token->key; # NOTE: request token, not valid for use!
        $sub->created = DB_DataObject_Cast::dateTime(); # current time

        if (!$sub->insert()) {
            return false;
        }

        return true;
    }

    function addAvatar($profile, $url)
    {
        $temp_filename = tempnam(sys_get_temp_dir(), 'listenee_avatar');
        copy($url, $temp_filename);
        $imagefile = new ImageFile($profile->id, $temp_filename);
        $filename = Avatar::filename($profile->id,
                                     image_type_to_extension($imagefile->type),
                                     null,
                                     common_timestamp());
        rename($temp_filename, Avatar::path($filename));
        return $profile->setOriginal($filename);
    }

    function showAcceptMessage($tok)
    {
        common_show_header(_('Subscription authorized'));
        $this->element('p', null,
                       _('The subscription has been authorized, but no '.
                         'callback URL was passed. Check with the site\'s instructions for '.
                         'details on how to authorize the subscription. Your subscription token is:'));
        $this->element('blockquote', 'token', $tok);
        common_show_footer();
    }

    function showRejectMessage($tok)
    {
        common_show_header(_('Subscription rejected'));
        $this->element('p', null,
                       _('The subscription has been rejected, but no '.
                         'callback URL was passed. Check with the site\'s instructions for '.
                         'details on how to fully reject the subscription.'));
        common_show_footer();
    }

    function storeRequest($req)
    {
        common_ensure_session();
        $_SESSION['userauthorizationrequest'] = $req;
    }

    function clearRequest()
    {
        common_ensure_session();
        unset($_SESSION['userauthorizationrequest']);
    }

    function getStoredRequest()
    {
        common_ensure_session();
        $req = $_SESSION['userauthorizationrequest'];
        return $req;
    }

    function getNewRequest()
    {
        common_remove_magic_from_request();
        $req = OAuthRequest::from_request();
        return $req;
    }

    # Throws an OAuthException if anything goes wrong

    function validateRequest(&$req)
    {
        # OAuth stuff -- have to copy from OAuth.php since they're
        # all private methods, and there's no user-authentication method
        $this->checkVersion($req);
        $datastore = omb_oauth_datastore();
        $consumer = $this->getConsumer($datastore, $req);
        $token = $this->getToken($datastore, $req, $consumer);
        $this->checkTimestamp($req);
        $this->checkNonce($datastore, $req, $consumer, $token);
        $this->checkSignature($req, $consumer, $token);
        $this->validateOmb($req);
        return true;
    }

    function validateOmb(&$req)
    {
        foreach (array('omb_version', 'omb_listener', 'omb_listenee',
                       'omb_listenee_profile', 'omb_listenee_nickname',
                       'omb_listenee_license') as $param)
        {
            if (is_null($req->get_parameter($param))) {
                throw new OAuthException("Required parameter '$param' not found");
            }
        }
        # Now, OMB stuff
        $version = $req->get_parameter('omb_version');
        if ($version != OMB_VERSION_01) {
            throw new OAuthException("OpenMicroBlogging version '$version' not supported");
        }
        $listener =    $req->get_parameter('omb_listener');
        $user = User::staticGet('uri', $listener);
        if (!$user) {
            throw new OAuthException("Listener URI '$listener' not found here");
        }
        $cur = common_current_user();
        if ($cur->id != $user->id) {
            throw new OAuthException("Can't add for another user!");
        }
        $listenee = $req->get_parameter('omb_listenee');
        if (!Validate::uri($listenee) &&
            !common_valid_tag($listenee)) {
            throw new OAuthException("Listenee URI '$listenee' not a recognizable URI");
        }
        if (strlen($listenee) > 255) {
            throw new OAuthException("Listenee URI '$listenee' too long");
        }

        $other = User::staticGet('uri', $listenee);
        if ($other) {
            throw new OAuthException("Listenee URI '$listenee' is local user");
        }

        $remote = Remote_profile::staticGet('uri', $listenee);
        if ($remote) {
            $sub = new Subscription();
            $sub->subscriber = $user->id;
            $sub->subscribed = $remote->id;
            if ($sub->find(true)) {
                throw new OAuthException("Already subscribed to user!");
            }
        }
        $nickname = $req->get_parameter('omb_listenee_nickname');
        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
            throw new OAuthException('Nickname must have only letters and numbers and no spaces.');
        }
        $profile = $req->get_parameter('omb_listenee_profile');
        if (!common_valid_http_url($profile)) {
            throw new OAuthException("Invalid profile URL '$profile'.");
        }

        if ($profile == common_local_url('showstream', array('nickname' => $nickname))) {
            throw new OAuthException("Profile URL '$profile' is for a local user.");
        }

        $license = $req->get_parameter('omb_listenee_license');
        if (!common_valid_http_url($license)) {
            throw new OAuthException("Invalid license URL '$license'.");
        }
        $site_license = common_config('license', 'url');
        if (!common_compatible_license($license, $site_license)) {
            throw new OAuthException("Listenee stream license '$license' not compatible with site license '$site_license'.");
        }
        # optional stuff
        $fullname = $req->get_parameter('omb_listenee_fullname');
        if ($fullname && mb_strlen($fullname) > 255) {
            throw new OAuthException("Full name '$fullname' too long.");
        }
        $homepage = $req->get_parameter('omb_listenee_homepage');
        if ($homepage && (!common_valid_http_url($homepage) || mb_strlen($homepage) > 255)) {
            throw new OAuthException("Invalid homepage '$homepage'");
        }
        $bio = $req->get_parameter('omb_listenee_bio');
        if ($bio && mb_strlen($bio) > 140) {
            throw new OAuthException("Bio too long '$bio'");
        }
        $location = $req->get_parameter('omb_listenee_location');
        if ($location && mb_strlen($location) > 255) {
            throw new OAuthException("Location too long '$location'");
        }
        $avatar = $req->get_parameter('omb_listenee_avatar');
        if ($avatar) {
            if (!common_valid_http_url($avatar) || strlen($avatar) > 255) {
                throw new OAuthException("Invalid avatar URL '$avatar'");
            }
            $size = @getimagesize($avatar);
            if (!$size) {
                throw new OAuthException("Can't read avatar URL '$avatar'");
            }
            if ($size[0] != AVATAR_PROFILE_SIZE || $size[1] != AVATAR_PROFILE_SIZE) {
                throw new OAuthException("Wrong size image at '$avatar'");
            }
            if (!in_array($size[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG,
                                          IMAGETYPE_PNG))) {
                throw new OAuthException("Wrong image type for '$avatar'");
            }
        }
        $callback = $req->get_parameter('oauth_callback');
        if ($callback && !common_valid_http_url($callback)) {
            throw new OAuthException("Invalid callback URL '$callback'");
        }
        if ($callback && $callback == common_local_url('finishremotesubscribe')) {
            throw new OAuthException("Callback URL '$callback' is for local site.");
        }
    }

    # Snagged from OAuthServer

    function checkVersion(&$req)
    {
        $version = $req->get_parameter("oauth_version");
        if (!$version) {
            $version = 1.0;
        }
        if ($version != 1.0) {
            throw new OAuthException("OAuth version '$version' not supported");
        }
        return $version;
    }

    # Snagged from OAuthServer

    function getConsumer($datastore, $req)
    {
        $consumer_key = @$req->get_parameter("oauth_consumer_key");
        if (!$consumer_key) {
            throw new OAuthException("Invalid consumer key");
        }

        $consumer = $datastore->lookup_consumer($consumer_key);
        if (!$consumer) {
            throw new OAuthException("Invalid consumer");
        }
        return $consumer;
    }

    # Mostly cadged from OAuthServer

    function getToken($datastore, &$req, $consumer)
    {/*{{{*/
        $token_field = @$req->get_parameter('oauth_token');
        $token = $datastore->lookup_token($consumer, 'request', $token_field);
        if (!$token) {
            throw new OAuthException("Invalid $token_type token: $token_field");
        }
        return $token;
    }

    function checkTimestamp(&$req)
    {
        $timestamp = @$req->get_parameter('oauth_timestamp');
        $now = time();
        if ($now - $timestamp > TIMESTAMP_THRESHOLD) {
            throw new OAuthException("Expired timestamp, yours $timestamp, ours $now");
        }
    }

    # NOTE: don't call twice on the same request; will fail!
    function checkNonce(&$datastore, &$req, $consumer, $token)
    {
        $timestamp = @$req->get_parameter('oauth_timestamp');
        $nonce = @$req->get_parameter('oauth_nonce');
        $found = $datastore->lookup_nonce($consumer, $token, $nonce, $timestamp);
        if ($found) {
            throw new OAuthException("Nonce already used");
        }
        return true;
    }

    function checkSignature(&$req, $consumer, $token)
    {
        $signature_method = $this->getSignatureMethod($req);
        $signature = $req->get_parameter('oauth_signature');
        $valid_sig = $signature_method->check_signature($req,
                                                        $consumer,
                                                        $token,
                                                        $signature);
        if (!$valid_sig) {
            throw new OAuthException("Invalid signature");
        }
    }

    function getSignatureMethod(&$req)
    {
        $signature_method = @$req->get_parameter("oauth_signature_method");
        if (!$signature_method) {
            $signature_method = "PLAINTEXT";
        }
        if ($signature_method != 'HMAC-SHA1') {
            throw new OAuthException("Signature method '$signature_method' not supported.");
        }
        return omb_hmac_sha1();
    }
}
