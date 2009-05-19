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

require_once(INSTALLDIR.'/lib/openid.php');

class FinishopenidloginAction extends Action
{
    var $error = null;
    var $username = null;
    var $message = null;

    function handle($args)
    {
        parent::handle($args);
        if (common_is_real_login()) {
            $this->clientError(_('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. Try again, please.'));
                return;
            }
            if ($this->arg('create')) {
                if (!$this->boolean('license')) {
                    $this->showForm(_('You can\'t register if you don\'t agree to the license.'),
                                    $this->trimmed('newname'));
                    return;
                }
                $this->createNewUser();
            } else if ($this->arg('connect')) {
                $this->connectUser();
            } else {
                common_debug(print_r($this->args, true), __FILE__);
                $this->showForm(_('Something weird happened.'),
                                $this->trimmed('newname'));
            }
        } else {
            $this->tryLogin();
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $this->element('div', 'instructions',
                           sprintf(_('This is the first time you\'ve logged into %s so we must connect your OpenID to a local account. You can either create a new account, or connect with your existing account, if you have one.'), common_config('site', 'name')));
        }
    }

    function title()
    {
        return _('OpenID Account Setup');
    }

    function showForm($error=null, $username=null)
    {
        $this->error = $error;
        $this->username = $username;

        $this->showPage();
    }

    function showContent()
    {
        if (!empty($this->message_text)) {
            $this->element('p', null, $this->message);
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'account_connect',
                                          'action' => common_local_url('finishopenidlogin')));
        $this->hidden('token', common_session_token());
        $this->element('h2', null,
                       _('Create new account'));
        $this->element('p', null,
                       _('Create a new user with this nickname.'));
        $this->input('newname', _('New nickname'),
                     ($this->username) ? $this->username : '',
                     _('1-64 lowercase letters or numbers, no punctuation or spaces'));
        $this->elementStart('p');
        $this->element('input', array('type' => 'checkbox',
                                      'id' => 'license',
                                      'name' => 'license',
                                      'value' => 'true'));
        $this->text(_('My text and files are available under '));
        $this->element('a', array('href' => common_config('license', 'url')),
                       common_config('license', 'title'));
        $this->text(_(' except this private data: password, email address, IM address, phone number.'));
        $this->elementEnd('p');
        $this->submit('create', _('Create'));
        $this->element('h2', null,
                       _('Connect existing account'));
        $this->element('p', null,
                       _('If you already have an account, login with your username and password to connect it to your OpenID.'));
        $this->input('nickname', _('Existing nickname'));
        $this->password('password', _('Password'));
        $this->submit('connect', _('Connect'));
        $this->elementEnd('form');
    }

    function tryLogin()
    {
        $consumer = oid_consumer();

        $response = $consumer->complete(common_local_url('finishopenidlogin'));

        if ($response->status == Auth_OpenID_CANCEL) {
            $this->message(_('OpenID authentication cancelled.'));
            return;
        } else if ($response->status == Auth_OpenID_FAILURE) {
            // Authentication failed; display the error message.
            $this->message(sprintf(_('OpenID authentication failed: %s'), $response->message));
        } else if ($response->status == Auth_OpenID_SUCCESS) {
            // This means the authentication succeeded; extract the
            // identity URL and Simple Registration data (if it was
            // returned).
            $display = $response->getDisplayIdentifier();
            $canonical = ($response->endpoint->canonicalID) ?
              $response->endpoint->canonicalID : $response->getDisplayIdentifier();

            $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);

            if ($sreg_resp) {
                $sreg = $sreg_resp->contents();
            }

            $user = oid_get_user($canonical);

            if ($user) {
                oid_set_last($display);
                # XXX: commented out at @edd's request until better
                # control over how data flows from OpenID provider.
                # oid_update_user($user, $sreg);
                common_set_user($user);
                common_real_login(true);
                if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
                    common_rememberme($user);
                }
                unset($_SESSION['openid_rememberme']);
                $this->goHome($user->nickname);
            } else {
                $this->saveValues($display, $canonical, $sreg);
                $this->showForm(null, $this->bestNewNickname($display, $sreg));
            }
        }
    }

    function message($msg)
    {
        $this->message_text = $msg;
        $this->showPage();
    }

    function saveValues($display, $canonical, $sreg)
    {
        common_ensure_session();
        $_SESSION['openid_display'] = $display;
        $_SESSION['openid_canonical'] = $canonical;
        $_SESSION['openid_sreg'] = $sreg;
    }

    function getSavedValues()
    {
        return array($_SESSION['openid_display'],
                     $_SESSION['openid_canonical'],
                     $_SESSION['openid_sreg']);
    }

    function createNewUser()
    {
        # FIXME: save invite code before redirect, and check here

        if (common_config('site', 'closed')) {
            $this->clientError(_('Registration not allowed.'));
            return;
        }

        $invite = null;

        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if (empty($code)) {
                $this->clientError(_('Registration not allowed.'));
                return;
            }

            $invite = Invitation::staticGet($code);

            if (empty($invite)) {
                $this->clientError(_('Not a valid invitation code.'));
                return;
            }
        }

        $nickname = $this->trimmed('newname');

        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
            $this->showForm(_('Nickname must have only lowercase letters and numbers and no spaces.'));
            return;
        }

        if (!User::allowed_nickname($nickname)) {
            $this->showForm(_('Nickname not allowed.'));
            return;
        }

        if (User::staticGet('nickname', $nickname)) {
            $this->showForm(_('Nickname already in use. Try another one.'));
            return;
        }

        list($display, $canonical, $sreg) = $this->getSavedValues();

        if (!$display || !$canonical) {
            $this->serverError(_('Stored OpenID not found.'));
            return;
        }

        # Possible race condition... let's be paranoid

        $other = oid_get_user($canonical);

        if ($other) {
            $this->serverError(_('Creating new account for OpenID that already has a user.'));
            return;
        }

        $location = '';
        if (!empty($sreg['country'])) {
            if ($sreg['postcode']) {
                # XXX: use postcode to get city and region
                # XXX: also, store postcode somewhere -- it's valuable!
                $location = $sreg['postcode'] . ', ' . $sreg['country'];
            } else {
                $location = $sreg['country'];
            }
        }

        if (!empty($sreg['fullname']) && mb_strlen($sreg['fullname']) <= 255) {
            $fullname = $sreg['fullname'];
        } else {
            $fullname = '';
        }

        if (!empty($sreg['email']) && Validate::email($sreg['email'], true)) {
            $email = $sreg['email'];
        } else {
            $email = '';
        }

        # XXX: add language
        # XXX: add timezone

        $args = array('nickname' => $nickname,
                      'email' => $email,
                      'fullname' => $fullname,
                      'location' => $location);

        if (!empty($invite)) {
            $args['code'] = $invite->code;
        }

        $user = User::register($args);

        $result = oid_link_user($user->id, $canonical, $display);

        oid_set_last($display);
        common_set_user($user);
        common_real_login(true);
        if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
            common_rememberme($user);
        }
        unset($_SESSION['openid_rememberme']);
        common_redirect(common_local_url('showstream', array('nickname' => $user->nickname)),
                        303);
    }

    function connectUser()
    {
        $nickname = $this->trimmed('nickname');
        $password = $this->trimmed('password');

        if (!common_check_user($nickname, $password)) {
            $this->showForm(_('Invalid username or password.'));
            return;
        }

        # They're legit!

        $user = User::staticGet('nickname', $nickname);

        list($display, $canonical, $sreg) = $this->getSavedValues();

        if (!$display || !$canonical) {
            $this->serverError(_('Stored OpenID not found.'));
            return;
        }

        $result = oid_link_user($user->id, $canonical, $display);

        if (!$result) {
            $this->serverError(_('Error connecting user to OpenID.'));
            return;
        }

        oid_update_user($user, $sreg);
        oid_set_last($display);
        common_set_user($user);
        common_real_login(true);
        if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
            common_rememberme($user);
        }
        unset($_SESSION['openid_rememberme']);
        $this->goHome($user->nickname);
    }

    function goHome($nickname)
    {
        $url = common_get_returnto();
        if ($url) {
            # We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $nickname));
        }
        common_redirect($url, 303);
    }

    function bestNewNickname($display, $sreg)
    {

        # Try the passed-in nickname

        if (!empty($sreg['nickname'])) {
            $nickname = $this->nicknamize($sreg['nickname']);
            if ($this->isNewNickname($nickname)) {
                return $nickname;
            }
        }

        # Try the full name

        if (!empty($sreg['fullname'])) {
            $fullname = $this->nicknamize($sreg['fullname']);
            if ($this->isNewNickname($fullname)) {
                return $fullname;
            }
        }

        # Try the URL

        $from_url = $this->openidToNickname($display);

        if ($from_url && $this->isNewNickname($from_url)) {
            return $from_url;
        }

        # XXX: others?

        return null;
    }

    function isNewNickname($str)
    {
        if (!Validate::string($str, array('min_length' => 1,
                                          'max_length' => 64,
                                          'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
            return false;
        }
        if (!User::allowed_nickname($str)) {
            return false;
        }
        if (User::staticGet('nickname', $str)) {
            return false;
        }
        return true;
    }

    function openidToNickname($openid)
    {
        if (Auth_Yadis_identifierScheme($openid) == 'XRI') {
            return $this->xriToNickname($openid);
        } else {
            return $this->urlToNickname($openid);
        }
    }

    # We try to use an OpenID URL as a legal Laconica user name in this order
    # 1. Plain hostname, like http://evanp.myopenid.com/
    # 2. One element in path, like http://profile.typekey.com/EvanProdromou/
    #    or http://getopenid.com/evanprodromou

    function urlToNickname($openid)
    {
        static $bad = array('query', 'user', 'password', 'port', 'fragment');

        $parts = parse_url($openid);

        # If any of these parts exist, this won't work

        foreach ($bad as $badpart) {
            if (array_key_exists($badpart, $parts)) {
                return null;
            }
        }

        # We just have host and/or path

        # If it's just a host...
        if (array_key_exists('host', $parts) &&
            (!array_key_exists('path', $parts) || strcmp($parts['path'], '/') == 0))
        {
            $hostparts = explode('.', $parts['host']);

            # Try to catch common idiom of nickname.service.tld

            if ((count($hostparts) > 2) &&
                (strlen($hostparts[count($hostparts) - 2]) > 3) && # try to skip .co.uk, .com.au
                (strcmp($hostparts[0], 'www') != 0))
            {
                return $this->nicknamize($hostparts[0]);
            } else {
                # Do the whole hostname
                return $this->nicknamize($parts['host']);
            }
        } else {
            if (array_key_exists('path', $parts)) {
                # Strip starting, ending slashes
                $path = preg_replace('@/$@', '', $parts['path']);
                $path = preg_replace('@^/@', '', $path);
                if (strpos($path, '/') === false) {
                    return $this->nicknamize($path);
                }
            }
        }

        return null;
    }

    function xriToNickname($xri)
    {
        $base = $this->xriBase($xri);

        if (!$base) {
            return null;
        } else {
            # =evan.prodromou
            # or @gratis*evan.prodromou
            $parts = explode('*', substr($base, 1));
            return $this->nicknamize(array_pop($parts));
        }
    }

    function xriBase($xri)
    {
        if (substr($xri, 0, 6) == 'xri://') {
            return substr($xri, 6);
        } else {
            return $xri;
        }
    }

    # Given a string, try to make it work as a nickname

    function nicknamize($str)
    {
        $str = preg_replace('/\W/', '', $str);
        return strtolower($str);
    }
}
