<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to enable Facebook Connect
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
 * @package   Laconica
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/FBConnect/FBConnectLogin.php';
require_once INSTALLDIR . '/lib/facebookutil.php';

/**
 * Plugin to enable Facebook Connect
 *
 * @category Plugin
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class FBConnectPlugin extends Plugin
{

    function __construct()
    {
        parent::__construct();
    }

    // Hook in new actions
    function onRouterInitialized(&$m) {
        $m->connect('main/facebookconnect', array('action' => 'fbconnectlogin'));
     }

    // Add in xmlns:fb
    function onStartShowHTML($action)
    {

        // XXX: This is probably a bad place to do general processing
        // so maybe I need to make some new events?  Maybe in
        // Action::prepare?

        $name = get_class($action);

        common_debug("action: $name");

        // Avoid a redirect loop
        if ($name != 'FBConnectloginAction') {

            $this->checkFacebookUser($action);

        }

        $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ?
        $_SERVER['HTTP_ACCEPT'] : null;

        // XXX: allow content negotiation for RDF, RSS, or XRDS

        $cp = common_accept_to_prefs($httpaccept);
        $sp = common_accept_to_prefs(PAGE_TYPE_PREFS);

        $type = common_negotiate_type($cp, $sp);

        if (!$type) {
            throw new ClientException(_('This page is not available in a '.
                                         'media type you accept'), 406);
        }


        header('Content-Type: '.$type);

        $action->extraHeaders();

        $action->startXML('html',
                         '-//W3C//DTD XHTML 1.0 Strict//EN',
                         'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');

        $language = $action->getLanguage();

        $action->elementStart('html', array('xmlns'  => 'http://www.w3.org/1999/xhtml',
                                            'xmlns:fb' => 'http://www.facebook.com/2008/fbml',
                                            'xml:lang' => $language,
                                            'lang'     => $language));

        return false;

    }

    function onEndShowLaconicaScripts($action)
    {

        $action->element('script',
            array('type' => 'text/javascript',
                  'src'  => 'http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php'),
                  ' ');

        $apikey = common_config('facebook', 'apikey');
        $plugin_path = common_path('plugins/FBConnect');

        $login_url = common_get_returnto() || common_local_url('public');

        $html = sprintf('<script type="text/javascript">FB.init("%s", "%s/xd_receiver.htm");

                            function refresh_page() {
                                window.location = "%s";
                            }

                         </script>', $apikey, $plugin_path, $login_url);


        $action->raw($html);
    }

    function onStartPrimaryNav($action)
    {
        $user = common_current_user();

        if ($user) {
             $action->menuItem(common_local_url('all', array('nickname' => $user->nickname)),
                             _('Home'), _('Personal profile and friends timeline'), false, 'nav_home');
             $action->menuItem(common_local_url('profilesettings'),
                             _('Account'), _('Change your email, avatar, password, profile'), false, 'nav_account');
             if (common_config('xmpp', 'enabled')) {
                 $action->menuItem(common_local_url('imsettings'),
                                 _('Connect'), _('Connect to IM, SMS, Twitter'), false, 'nav_connect');
             } else {
                 $action->menuItem(common_local_url('smssettings'),
                                 _('Connect'), _('Connect to SMS, Twitter'), false, 'nav_connect');
             }
             $action->menuItem(common_local_url('invite'),
                              _('Invite'),
                              sprintf(_('Invite friends and colleagues to join you on %s'),
                              common_config('site', 'name')),
                              false, 'nav_invitecontact');

             // Need to override the Logout link to make it do FB stuff

             $logout_url = common_local_url('logout');
             $title =  _('Logout from the site');
             $text = _('Logout');

             $html = sprintf('<li id="nav_logout"><a href="%s" title="%s" ' .
                 'onclick="FB.Connect.logoutAndRedirect(\'%s\')">%s</a></li>',
                    $logout_url, $title, $logout_url, $text);

             $action->raw($html);

         }
         else {
             if (!common_config('site', 'closed')) {
                 $action->menuItem(common_local_url('register'),
                                 _('Register'), _('Create an account'), false, 'nav_register');
             }
             $action->menuItem(common_local_url('openidlogin'),
                             _('OpenID'), _('Login with OpenID'), false, 'nav_openid');
             $action->menuItem(common_local_url('login'),
                             _('Login'), _('Login to the site'), false, 'nav_login');
         }

         $action->menuItem(common_local_url('doc', array('title' => 'help')),
                         _('Help'), _('Help me!'), false, 'nav_help');
         $action->menuItem(common_local_url('peoplesearch'),
                         _('Search'), _('Search for people or text'), false, 'nav_search');

        // Tack on "Connect with Facebook" button

        // XXX: Maybe this looks bad and should not go here.  Where should it go?

        if (!$user) {
             $action->elementStart('li');
             $action->element('fb:login-button', array('onlogin' => 'refresh_page()',
                 'length' => 'long'));
             $action->elementEnd('li');
        }

        return false;
    }

    function checkFacebookUser() {

        try {

            $facebook = getFacebook();
            $fbuid = $facebook->get_loggedin_user();
            $user = common_current_user();

            // If you're a Facebook user and you're logged in do nothing

            // If you're a Facebook user and you're not logged in
            // redirect to Facebook connect login page because that means you have clicked
            // the 'connect with Facebook' button and have cookies

            if ($fbuid > 0) {

                if ($facebook->api_client->users_isAppUser($fbuid) ||
                    $facebook->api_client->added) {

                    // user should be connected...

                    common_debug("Facebook user found: $fbuid");

                    if ($user) {
                        common_debug("Facebook user is logged in.");
                        return;

                    } else {
                        common_debug("Facebook user is NOT logged in.");
                        common_redirect(common_local_url('fbconnectlogin'), 303);
                    }

                } else {
                    common_debug("No Facebook connect user found.");
                }
            }

        } catch (Exception $e) {
            common_debug('Expired FB session.');
        }

    }

    function onStartLogout($action)
    {
        common_debug("onEndLogout()");

        common_set_user(null);
        common_real_login(false); // not logged in
        common_forgetme(); // don't log back in!

        try {

            $facebook = getFacebook();
            $fbuid = $facebook->get_loggedin_user();

            // XXX: ARGGGH this doesn't work right!

            if ($fbuid) {
                $facebook->expire_session();
                $facebook->logout(common_local_url('public'));
              }

        } catch (Exception $e) {
            common_debug('Problem expiring FB session');
        }

        common_debug("logged out.");

        return false;
    }

}


