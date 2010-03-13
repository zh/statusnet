<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Authorize an OAuth request token
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
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apioauth.php';

/**
 * Authorize an OAuth request token
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthAuthorizeAction extends ApiOauthAction
{
    var $oauth_token;
    var $callback;
    var $app;
    var $nickname;
    var $password;
    var $store;

    /**
     * Is this a read-only action?
     *
     * @return boolean false
     */

    function isReadOnly($args)
    {
        return false;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $this->nickname    = $this->trimmed('nickname');
        $this->password    = $this->arg('password');
        $this->oauth_token = $this->arg('oauth_token');
        $this->callback    = $this->arg('oauth_callback');
        $this->store       = new ApiStatusNetOAuthDataStore();
        $this->app         = $this->store->getAppByRequestToken($this->oauth_token);

        return true;
    }

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $this->handlePost();

        } else {

            if (empty($this->oauth_token)) {
                $this->clientError(_('No oauth_token parameter provided.'));
                return;
            }

            if (empty($this->app)) {
                $this->clientError(_('Invalid token.'));
                return;
            }

            $name = $this->app->name;

            $this->showForm();
        }
    }

    function handlePost()
    {
        // check session token for CSRF protection.

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        // check creds

        $user = null;

        if (!common_logged_in()) {
            $user = common_check_user($this->nickname, $this->password);
            if (empty($user)) {
                $this->showForm(_("Invalid nickname / password!"));
                return;
            }
        } else {
            $user = common_current_user();
        }

        if ($this->arg('allow')) {

            // mark the req token as authorized

            $this->store->authorize_token($this->oauth_token);

            // Check to see if there was a previous token associated
            // with this user/app and kill it. If the user is doing this she
            // probably doesn't want any old tokens anyway.

            $appUser = Oauth_application_user::getByKeys($user, $this->app);

            if (!empty($appUser)) {
                $result = $appUser->delete();

                if (!$result) {
                    common_log_db_error($appUser, 'DELETE', __FILE__);
                    throw new ServerException(_('Database error deleting OAuth application user.'));
                    return;
                }
            }

            // associated the authorized req token with the user and the app

            $appUser = new Oauth_application_user();

            $appUser->profile_id     = $user->id;
            $appUser->application_id = $this->app->id;

            // Note: do not copy the access type from the application.
            // The access type should always be 0 when the OAuth app
            // user record has a request token associated with it.
            // Access type gets assigned once an access token has been
            // granted.  The OAuth app user record then gets updated
            // with the new access token and access type.

            $appUser->token          = $this->oauth_token;
            $appUser->created        = common_sql_now();

            $result = $appUser->insert();

            if (!$result) {
                common_log_db_error($appUser, 'INSERT', __FILE__);
                throw new ServerException(_('Database error inserting OAuth application user.'));
                return;
            }

            // if we have a callback redirect and provide the token

            // A callback specified in the app setup overrides whatever
            // is passed in with the request.

            if (!empty($this->app->callback_url)) {
                $this->callback = $this->app->callback_url;
            }

            if (!empty($this->callback)) {

                $target_url = $this->getCallback($this->callback,
                                                 array('oauth_token' => $this->oauth_token));

                common_redirect($target_url, 303);
            } else {
                common_debug("callback was empty!");
            }

            // otherwise inform the user that the rt was authorized

            $this->elementStart('p');

            // XXX: Do OAuth 1.0a verifier code

            $this->raw(sprintf(_("The request token %s has been authorized. " .
                                 'Please exchange it for an access token.'),
                               $this->oauth_token));

            $this->elementEnd('p');

        } else if ($this->arg('deny')) {

            $datastore = new ApiStatusNetOAuthDataStore();
            $datastore->revoke_token($this->oauth_token, 0);

            $this->elementStart('p');

            $this->raw(sprintf(_("The request token %s has been denied and revoked."),
                               $this->oauth_token));

            $this->elementEnd('p');
        } else {
            $this->clientError(_('Unexpected form submission.'));
            return;
        }
    }

    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    function showScripts()
    {
        parent::showScripts();
        if (!common_logged_in()) {
            $this->autofocus('nickname');
        }
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */

    function title()
    {
        return _('An application would like to connect to your account');
    }

    /**
     * Shows the authorization form.
     *
     * @return void
     */

    function showContent()
    {
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_apioauthauthorize',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('apioauthauthorize')));
        $this->elementStart('fieldset');
        $this->element('legend', array('id' => 'apioauthauthorize_allowdeny'),
                                 _('Allow or deny access'));

        $this->hidden('token', common_session_token());
        $this->hidden('oauth_token', $this->oauth_token);
        $this->hidden('oauth_callback', $this->callback);

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->elementStart('p');
        if (!empty($this->app->icon)) {
            $this->element('img', array('src' => $this->app->icon));
        }

        $access = ($this->app->access_type & Oauth_application::$writeAccess) ?
          'access and update' : 'access';

        $msg = _('The application <strong>%1$s</strong> by ' .
                 '<strong>%2$s</strong> would like the ability ' .
                 'to <strong>%3$s</strong> your %4$s account data. ' .
                 'You should only give access to your %4$s account ' .
                 'to third parties you trust.');

        $this->raw(sprintf($msg,
                           $this->app->name,
                           $this->app->organization,
                           $access,
                           common_config('site', 'name')));
        $this->elementEnd('p');
        $this->elementEnd('li');
        $this->elementEnd('ul');

        if (!common_logged_in()) {

            $this->elementStart('fieldset');
            $this->element('legend', null, _('Account'));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->input('nickname', _('Nickname'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->password('password', _('Password'));
            $this->elementEnd('li');
            $this->elementEnd('ul');

            $this->elementEnd('fieldset');

        }

        $this->element('input', array('id' => 'deny_submit',
                                      'class' => 'submit submit form_action-primary',
                                      'name' => 'deny',
                                      'type' => 'submit',
                                      'value' => _('Deny')));

        $this->element('input', array('id' => 'allow_submit',
                                      'class' => 'submit submit form_action-secondary',
                                      'name' => 'allow',
                                      'type' => 'submit',
                                      'value' => _('Allow')));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Instructions for using the form
     *
     * For "remembered" logins, we make the user re-login when they
     * try to change settings. Different instructions for this case.
     *
     * @return void
     */

    function getInstructions()
    {
        return _('Allow or deny access to your account information.');
    }

    /**
     * A local menu
     *
     * Shows different login/register actions.
     *
     * @return void
     */

    function showLocalNav()
    {
        // NOP
    }

    /**
     * Show site notice.
     *
     * @return nothing
     */

    function showSiteNotice()
    {
        // NOP
    }

    /**
     * Show notice form.
     *
     * Show the form for posting a new notice
     *
     * @return nothing
     */

    function showNoticeForm()
    {
        // NOP
    }

}
