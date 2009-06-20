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

class RemotesubscribeAction extends Action
{
    var $nickname;
    var $profile_url;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            $this->clientError(_('You can use the local subscription!'));
            return false;
        }

        $this->nickname = $this->trimmed('nickname');
        $this->profile_url = $this->trimmed('profile_url');

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            # CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }
            $this->remoteSubscription();
        } else {
            $this->showForm();
        }
    }

    function showForm($err=null)
    {
        $this->err = $err;
        $this->showPage();
    }

    function showPageNotice()
    {
        if ($this->err) {
            $this->element('div', 'error', $this->err);
        } else {
            $inst = _('To subscribe, you can [login](%%action.login%%),' .
                      ' or [register](%%action.register%%) a new ' .
                      ' account. If you already have an account ' .
                      ' on a [compatible microblogging site](%%doc.openmublog%%), ' .
                      ' enter your profile URL below.');
            $output = common_markup_to_html($inst);
            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    function title()
    {
        return _('Remote subscribe');
    }

    function showContent()
    {
        # id = remotesubscribe conflicts with the
        # button on profile page
        $this->elementStart('form', array('id' => 'form_remote_subscribe',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('remotesubscribe')));
        $this->elementStart('fieldset');
        $this->element('legend', _('Subscribe to a remote user'));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('nickname', _('User nickname'), $this->nickname,
                     _('Nickname of the user you want to follow'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->input('profile_url', _('Profile URL'), $this->profile_url,
                     _('URL of your profile on another compatible microblogging service'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', _('Subscribe'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function remoteSubscription()
    {
        $user = $this->getUser();

        if (!$user) {
            $this->showForm(_('No such user.'));
            return;
        }

        $this->profile_url = $this->trimmed('profile_url');

        if (!$this->profile_url) {
            $this->showForm(_('No such user.'));
            return;
        }

        if (!Validate::uri($this->profile_url, array('allowed_schemes' => array('http', 'https')))) {
            $this->showForm(_('Invalid profile URL (bad format)'));
            return;
        }

        $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();
        $yadis = Auth_Yadis_Yadis::discover($this->profile_url, $fetcher);

        if (!$yadis || $yadis->failed) {
            $this->showForm(_('Not a valid profile URL (no YADIS document).'));
            return;
        }

        # XXX: a little liberal for sites that accidentally put whitespace before the xml declaration

        $xrds =& Auth_Yadis_XRDS::parseXRDS(trim($yadis->response_text));

        if (!$xrds) {
            $this->showForm(_('Not a valid profile URL (no XRDS defined).'));
            return;
        }

        $omb = $this->getOmb($xrds);

        if (!$omb) {
            $this->showForm(_('Not a valid profile URL (incorrect services).'));
            return;
        }

        if (omb_service_uri($omb[OAUTH_ENDPOINT_REQUEST]) ==
            common_local_url('requesttoken'))
        {
            $this->showForm(_('That\'s a local profile! Login to subscribe.'));
            return;
        }

        if (User::staticGet('uri', omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]))) {
            $this->showForm(_('That\'s a local profile! Login to subscribe.'));
            return;
        }

        list($token, $secret) = $this->requestToken($omb);

        if (!$token || !$secret) {
            $this->showForm(_('Couldn\'t get a request token.'));
            return;
        }

        $this->requestAuthorization($user, $omb, $token, $secret);
    }

    function getUser()
    {
        $user = null;
        if ($this->nickname) {
            $user = User::staticGet('nickname', $this->nickname);
        }
        return $user;
    }

    function getOmb($xrds)
    {
        static $omb_endpoints = array(OMB_ENDPOINT_UPDATEPROFILE, OMB_ENDPOINT_POSTNOTICE);
        static $oauth_endpoints = array(OAUTH_ENDPOINT_REQUEST, OAUTH_ENDPOINT_AUTHORIZE,
                                        OAUTH_ENDPOINT_ACCESS);
        $omb = array();

        # XXX: the following code could probably be refactored to eliminate dupes

        $oauth_services = omb_get_services($xrds, OAUTH_DISCOVERY);

        if (!$oauth_services) {
            return null;
        }

        $oauth_service = $oauth_services[0];

        $oauth_xrd = $this->getXRD($oauth_service, $xrds);

        if (!$oauth_xrd) {
            return null;
        }

        if (!$this->addServices($oauth_xrd, $oauth_endpoints, $omb)) {
            return null;
        }

        $omb_services = omb_get_services($xrds, OMB_NAMESPACE);

        if (!$omb_services) {
            return null;
        }

        $omb_service = $omb_services[0];

        $omb_xrd = $this->getXRD($omb_service, $xrds);

        if (!$omb_xrd) {
            return null;
        }

        if (!$this->addServices($omb_xrd, $omb_endpoints, $omb)) {
            return null;
        }

        # XXX: check that we got all the services we needed

        foreach (array_merge($omb_endpoints, $oauth_endpoints) as $type) {
            if (!array_key_exists($type, $omb) || !$omb[$type]) {
                return null;
            }
        }

        if (!omb_local_id($omb[OAUTH_ENDPOINT_REQUEST])) {
            return null;
        }

        return $omb;
    }

    function getXRD($main_service, $main_xrds)
    {
        $uri = omb_service_uri($main_service);
        if (strpos($uri, "#") !== 0) {
            # FIXME: more rigorous handling of external service definitions
            return null;
        }
        $id = substr($uri, 1);
        $nodes = $main_xrds->allXrdNodes;
        $parser = $main_xrds->parser;
        foreach ($nodes as $node) {
            $attrs = $parser->attributes($node);
            if (array_key_exists('xml:id', $attrs) &&
                $attrs['xml:id'] == $id) {
                # XXX: trick the constructor into thinking this is the only node
                $bogus_nodes = array($node);
                return new Auth_Yadis_XRDS($parser, $bogus_nodes);
            }
        }
        return null;
    }

    function addServices($xrd, $types, &$omb)
    {
        foreach ($types as $type) {
            $matches = omb_get_services($xrd, $type);
            if ($matches) {
                $omb[$type] = $matches[0];
            } else {
                # no match for type
                return false;
            }
        }
        return true;
    }

    function requestToken($omb)
    {
        $con = omb_oauth_consumer();

        $url = omb_service_uri($omb[OAUTH_ENDPOINT_REQUEST]);

        # XXX: Is this the right thing to do? Strip off GET params and make them
        # POST params? Seems wrong to me.

        $parsed = parse_url($url);
        $params = array();
        parse_str($parsed['query'], $params);

        $req = OAuthRequest::from_consumer_and_token($con, null, "POST", $url, $params);

        $listener = omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]);

        if (!$listener) {
            return null;
        }

        $req->set_parameter('omb_listener', $listener);
        $req->set_parameter('omb_version', OMB_VERSION_01);

        # XXX: test to see if endpoint accepts this signature method

        $req->sign_request(omb_hmac_sha1(), $con, null);

        # We re-use this tool's fetcher, since it's pretty good

        $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();

        $result = $fetcher->post($req->get_normalized_http_url(),
                                 $req->to_postdata(),
                                 array('User-Agent: Laconica/' . LACONICA_VERSION));
        if ($result->status != 200) {
            return null;
        }

        parse_str($result->body, $return);

        return array($return['oauth_token'], $return['oauth_token_secret']);
    }

    function requestAuthorization($user, $omb, $token, $secret)
    {
        $con = omb_oauth_consumer();
        $tok = new OAuthToken($token, $secret);

        $url = omb_service_uri($omb[OAUTH_ENDPOINT_AUTHORIZE]);

        # XXX: Is this the right thing to do? Strip off GET params and make them
        # POST params? Seems wrong to me.

        $parsed = parse_url($url);
        $params = array();
        parse_str($parsed['query'], $params);

        $req = OAuthRequest::from_consumer_and_token($con, $tok, 'GET', $url, $params);

        # We send over a ton of information. This lets the other
        # server store info about our user, and it lets the current
        # user decide if they really want to authorize the subscription.

        $req->set_parameter('omb_version', OMB_VERSION_01);
        $req->set_parameter('omb_listener', omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]));
        $req->set_parameter('omb_listenee', $user->uri);
        $req->set_parameter('omb_listenee_profile', common_profile_url($user->nickname));
        $req->set_parameter('omb_listenee_nickname', $user->nickname);
        $req->set_parameter('omb_listenee_license', common_config('license', 'url'));

        $profile = $user->getProfile();
        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            $this->serverError(_('User without matching profile'));
            return;
        }

        if (!is_null($profile->fullname)) {
            $req->set_parameter('omb_listenee_fullname', $profile->fullname);
        }
        if (!is_null($profile->homepage)) {
            $req->set_parameter('omb_listenee_homepage', $profile->homepage);
        }
        if (!is_null($profile->bio)) {
            $req->set_parameter('omb_listenee_bio', $profile->bio);
        }
        if (!is_null($profile->location)) {
            $req->set_parameter('omb_listenee_location', $profile->location);
        }
        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        if ($avatar) {
            $req->set_parameter('omb_listenee_avatar', $avatar->url);
        }

        # XXX: add a nonce to prevent replay attacks

        $req->set_parameter('oauth_callback', common_local_url('finishremotesubscribe'));

        # XXX: test to see if endpoint accepts this signature method

        $req->sign_request(omb_hmac_sha1(), $con, $tok);

        # store all our info here

        $omb['listenee'] = $user->nickname;
        $omb['listener'] = omb_local_id($omb[OAUTH_ENDPOINT_REQUEST]);
        $omb['token'] = $token;
        $omb['secret'] = $secret;
        # call doesn't work after bounce back so we cache; maybe serialization issue...?
        $omb['access_token_url'] = omb_service_uri($omb[OAUTH_ENDPOINT_ACCESS]);
        $omb['post_notice_url'] = omb_service_uri($omb[OMB_ENDPOINT_POSTNOTICE]);
        $omb['update_profile_url'] = omb_service_uri($omb[OMB_ENDPOINT_UPDATEPROFILE]);

        common_ensure_session();

        $_SESSION['oauth_authorization_request'] = $omb;

        # Redirect to authorization service

        common_redirect($req->to_url(), 303);
        return;
    }
}
