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

require_once(INSTALLDIR.'/lib/omb.php');
define('TIMESTAMP_THRESHOLD', 300);

class UserauthorizationAction extends Action
{
    var $error;
    var $params;

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            # CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $params = $this->getStoredParams();
                $this->showForm($params, _('There was a problem with your session token. '.
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
                $this->validateRequest();
                $this->storeParams($_GET);
                $this->showForm($_GET);
            } catch (OAuthException $e) {
                $this->clearParams();
                $this->clientError($e->getMessage());
                return;
            }

        }
    }

    function showForm($params, $error=null)
    {
        $this->params = $params;
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
        $params = $this->params;

        $nickname = $params['omb_listenee_nickname'];
        $profile = $params['omb_listenee_profile'];
        $license = $params['omb_listenee_license'];
        $fullname = $params['omb_listenee_fullname'];
        $homepage = $params['omb_listenee_homepage'];
        $bio = $params['omb_listenee_bio'];
        $location = $params['omb_listenee_location'];
        $avatar = $params['omb_listenee_avatar'];

        $this->elementStart('div', array('class' => 'profile'));
        $this->elementStart('div', 'entity_profile vcard');
        $this->elementStart('a', array('href' => $profile,
                                            'class' => 'url'));
        if ($avatar) {
            $this->element('img', array('src' => $avatar,
                                        'class' => 'photo avatar',
                                        'width' => AVATAR_PROFILE_SIZE,
                                        'height' => AVATAR_PROFILE_SIZE,
                                        'alt' => $nickname));
        }
        $hasFN = ($fullname !== '') ? 'nickname' : 'fn nickname';
        $this->elementStart('span', $hasFN);
        $this->raw($nickname);
        $this->elementEnd('span');
        $this->elementEnd('a');

        if (!is_null($fullname)) {
            $this->elementStart('dl', 'entity_fn');
            $this->elementStart('dd');
            $this->elementStart('span', 'fn');
            $this->raw($fullname);
            $this->elementEnd('span');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        if (!is_null($location)) {
            $this->elementStart('dl', 'entity_location');
            $this->element('dt', null, _('Location'));
            $this->elementStart('dd', 'label');
            $this->raw($location);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if (!is_null($homepage)) {
            $this->elementStart('dl', 'entity_url');
            $this->element('dt', null, _('URL'));
            $this->elementStart('dd');
            $this->elementStart('a', array('href' => $homepage,
                                                'class' => 'url'));
            $this->raw($homepage);
            $this->elementEnd('a');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if (!is_null($bio)) {
            $this->elementStart('dl', 'entity_note');
            $this->element('dt', null, _('Note'));
            $this->elementStart('dd', 'note');
            $this->raw($bio);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if (!is_null($license)) {
            $this->elementStart('dl', 'entity_license');
            $this->element('dt', null, _('License'));
            $this->elementStart('dd', 'license');
            $this->element('a', array('href' => $license,
                                      'class' => 'license'),
                           $license);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');

        $this->elementStart('div', 'entity_actions');
        $this->elementStart('ul');
        $this->elementStart('li', 'entity_subscribe');
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'userauthorization',
                                          'class' => 'form_user_authorization',
                                          'name' => 'userauthorization',
                                          'action' => common_local_url('userauthorization')));
        $this->hidden('token', common_session_token());

        $this->submit('accept', _('Accept'), 'submit accept', null, _('Subscribe to this user'));
        $this->submit('reject', _('Reject'), 'submit reject', null, _('Reject this subscription'));
        $this->elementEnd('form');
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    function sendAuthorization()
    {
        $params = $this->getStoredParams();

        if (!$params) {
            $this->clientError(_('No authorization request!'));
            return;
        }

        $callback = $params['oauth_callback'];

        if ($this->arg('accept')) {
            if (!$this->authorizeToken($params)) {
                $this->clientError(_('Error authorizing token'));
            }
            if (!$this->saveRemoteProfile($params)) {
                $this->clientError(_('Error saving remote profile'));
            }
            if (!$callback) {
                $this->showAcceptMessage($params['oauth_token']);
            } else {
                $newparams = array();
                $newparams['oauth_token'] = $params['oauth_token'];
                $newparams['omb_version'] = OMB_VERSION_01;
                $user = User::staticGet('uri', $params['omb_listener']);
                $profile = $user->getProfile();
                if (!$profile) {
                    common_log_db_error($user, 'SELECT', __FILE__);
                    $this->serverError(_('User without matching profile'));
                    return;
                }
                $newparams['omb_listener_nickname'] = $user->nickname;
                $newparams['omb_listener_profile'] = common_local_url('showstream',
                                                                   array('nickname' => $user->nickname));
                if (!is_null($profile->fullname)) {
                    $newparams['omb_listener_fullname'] = $profile->fullname;
                }
                if (!is_null($profile->homepage)) {
                    $newparams['omb_listener_homepage'] = $profile->homepage;
                }
                if (!is_null($profile->bio)) {
                    $newparams['omb_listener_bio'] = $profile->bio;
                }
                if (!is_null($profile->location)) {
                    $newparams['omb_listener_location'] = $profile->location;
                }
                $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
                if ($avatar) {
                    $newparams['omb_listener_avatar'] = $avatar->url;
                }
                $parts = array();
                foreach ($newparams as $k => $v) {
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

    function authorizeToken(&$params)
    {
        $token_field = $params['oauth_token'];
        $rt = new Token();
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

    function saveRemoteProfile(&$params)
    {
        # FIXME: we should really do this when the consumer comes
        # back for an access token. If they never do, we've got stuff in a
        # weird state.

        $nickname = $params['omb_listenee_nickname'];
        $fullname = $params['omb_listenee_fullname'];
        $profile_url = $params['omb_listenee_profile'];
        $homepage = $params['omb_listenee_homepage'];
        $bio = $params['omb_listenee_bio'];
        $location = $params['omb_listenee_location'];
        $avatar_url = $params['omb_listenee_avatar'];

        $listenee = $params['omb_listenee'];
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

        $sub = new Subscription();
        $sub->subscriber = $user->id;
        $sub->subscribed = $remote->id;
        $sub->token = $params['oauth_token']; # NOTE: request token, not valid for use!
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

    function storeParams($params)
    {
        common_ensure_session();
        $_SESSION['userauthorizationparams'] = $params;
    }

    function clearParams()
    {
        common_ensure_session();
        unset($_SESSION['userauthorizationparams']);
    }

    function getStoredParams()
    {
        common_ensure_session();
        $params = $_SESSION['userauthorizationparams'];
        return $params;
    }

    # Throws an OAuthException if anything goes wrong

    function validateRequest()
    {
        /* Find token.
           TODO: If no token is passed the user should get a prompt to enter it
                 according to OAuth Core 1.0 */
        $t = new Token();
        $t->tok = $_GET['oauth_token'];
        $t->type = 0;
        if (!$t->find(true)) {
            throw new OAuthException("Invalid request token: " . $_GET['oauth_token']);
        }

        $this->validateOmb();
        return true;
    }

    function validateOmb()
    {
        foreach (array('omb_version', 'omb_listener', 'omb_listenee',
                       'omb_listenee_profile', 'omb_listenee_nickname',
                       'omb_listenee_license') as $param)
        {
            if (!isset($_GET[$param]) || is_null($_GET[$param])) {
                throw new OAuthException("Required parameter '$param' not found");
            }
        }
        # Now, OMB stuff
        $version = $_GET['omb_version'];
        if ($version != OMB_VERSION_01) {
            throw new OAuthException("OpenMicroBlogging version '$version' not supported");
        }
        $listener = $_GET['omb_listener'];
        $user = User::staticGet('uri', $listener);
        if (!$user) {
            throw new OAuthException("Listener URI '$listener' not found here");
        }
        $cur = common_current_user();
        if ($cur->id != $user->id) {
            throw new OAuthException("Can't add for another user!");
        }
        $listenee = $_GET['omb_listenee'];
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
        $nickname = $_GET['omb_listenee_nickname'];
        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
            throw new OAuthException('Nickname must have only letters and numbers and no spaces.');
        }
        $profile = $_GET['omb_listenee_profile'];
        if (!common_valid_http_url($profile)) {
            throw new OAuthException("Invalid profile URL '$profile'.");
        }

        if ($profile == common_local_url('showstream', array('nickname' => $nickname))) {
            throw new OAuthException("Profile URL '$profile' is for a local user.");
        }

        $license = $_GET['omb_listenee_license'];
        if (!common_valid_http_url($license)) {
            throw new OAuthException("Invalid license URL '$license'.");
        }
        $site_license = common_config('license', 'url');
        if (!common_compatible_license($license, $site_license)) {
            throw new OAuthException("Listenee stream license '$license' not compatible with site license '$site_license'.");
        }
        # optional stuff
        $fullname = $_GET['omb_listenee_fullname'];
        if ($fullname && mb_strlen($fullname) > 255) {
            throw new OAuthException("Full name '$fullname' too long.");
        }
        $homepage = $_GET['omb_listenee_homepage'];
        if ($homepage && (!common_valid_http_url($homepage) || mb_strlen($homepage) > 255)) {
            throw new OAuthException("Invalid homepage '$homepage'");
        }
        $bio = $_GET['omb_listenee_bio'];
        if ($bio && mb_strlen($bio) > 140) {
            throw new OAuthException("Bio too long '$bio'");
        }
        $location = $_GET['omb_listenee_location'];
        if ($location && mb_strlen($location) > 255) {
            throw new OAuthException("Location too long '$location'");
        }
        $avatar = $_GET['omb_listenee_avatar'];
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
        $callback = $_GET['oauth_callback'];
        if ($callback && !common_valid_http_url($callback)) {
            throw new OAuthException("Invalid callback URL '$callback'");
        }
        if ($callback && $callback == common_local_url('finishremotesubscribe')) {
            throw new OAuthException("Callback URL '$callback' is for local site.");
        }
    }
}
