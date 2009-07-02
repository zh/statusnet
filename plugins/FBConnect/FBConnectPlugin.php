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

define("FACEBOOK_CONNECT_SERVICE", 3);

require_once INSTALLDIR . '/lib/facebookutil.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBConnectAuth.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBConnectLogin.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBConnectSettings.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBCLoginGroupNav.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBCSettingsNav.php';
require_once INSTALLDIR . '/plugins/FBConnect/FBC_XDReceiver.php';

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
        $m->connect('main/facebookconnect', array('action' => 'FBConnectAuth'));
        $m->connect('main/facebooklogin', array('action' => 'FBConnectLogin'));
        $m->connect('settings/facebook', array('action' => 'FBConnectSettings'));
        $m->connect('xd_receiver.html', array('action' => 'FBC_XDReceiver'));
     }

    // Add in xmlns:fb
    function onStartShowHTML($action)
    {

        if ($this->reqFbScripts($action)) {

            // XXX: Horrible hack to make Safari, FF2, and Chrome work with
            // Facebook Connect. These browser cannot use Facebook's
            // DOM parsing routines unless the mime type of the page is
            // text/html even though Facebook Connect uses XHTML.  This is
            // A bug in Facebook Connect, and this is a temporary solution
            // until they fix their JavaScript libs.
            header('Content-Type: text/html');

            $action->extraHeaders();

            $action->startXML('html',
                '-//W3C//DTD XHTML 1.0 Strict//EN',
                'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');

            $language = $action->getLanguage();

            $action->elementStart('html',
                array('xmlns'  => 'http://www.w3.org/1999/xhtml',
                      'xmlns:fb' => 'http://www.facebook.com/2008/fbml',
                      'xml:lang' => $language,
                      'lang'     => $language));

            return false;

        } else {

            return true;
        }
    }

    // Note: this script needs to appear in the <body>

    function onStartShowHeader($action)
    {
        if ($this->reqFbScripts($action)) {

            $apikey = common_config('facebook', 'apikey');
            $plugin_path = common_path('plugins/FBConnect');

            $login_url = common_local_url('FBConnectAuth');
            $logout_url = common_local_url('logout');

            // XXX: Facebook says we don't need this FB_RequireFeatures(),
            // but we actually do, for IE and Safari. Gar.

            $html = sprintf('<script type="text/javascript">
                                window.onload = function () {
                                    FB_RequireFeatures(
                                        ["XFBML"],
                                            function() {
                                                FB.Facebook.init("%s", "../xd_receiver.html");
                                            }
                                        ); }

                                function goto_login() {
                                    window.location = "%s";
                                }

                                function goto_logout() {
                                    window.location = "%s";
                                }
                              </script>', $apikey,
                                  $login_url, $logout_url);

            $action->raw($html);
        }

    }

    // Note: this script needs to appear as close as possible to </body>

    function onEndShowFooter($action)
    {
        if ($this->reqFbScripts($action)) {

            $action->element('script',
                array('type' => 'text/javascript',
                      'src'  => 'http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php'),
                      '');
        }
    }

    function onEndShowLaconicaStyles($action)
    {

        if ($this->reqFbScripts($action)) {

            $action->element('link', array('rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => common_path('plugins/FBConnect/FBConnectPlugin.css')));
        }
    }

    /**
     * Does the Action we're plugged into require the FB Scripts?  We only
     * want to output FB namespace, scripts, CSS, etc. on the pages that
     * really need them.
     *
     * @param Action the action in question
     *
     * @return boolean true
     */

    function reqFbScripts($action) {

        // If you're logged in w/FB Connect, you always need the FB stuff

        $fbuid = $this->loggedIn();

        if (!empty($fbuid)) {
            return true;
        }

        // List of actions that require FB stuff

        $needy = array('FBConnectLoginAction',
                       'FBConnectauthAction',
                       'FBConnectSettingsAction');

        if (in_array(get_class($action), $needy)) {
            return true;
        }

        return false;

    }

    /**
     * Is the user currently logged in with FB Connect?
     *
     * @return mixed $fbuid the Facebook ID of the logged in user, or null
     */

    function loggedIn()
    {
        $user = common_current_user();

        if (!empty($user)) {

            $flink = Foreign_link::getByUserId($user->id,
                FACEBOOK_CONNECT_SERVICE);
            $fbuid = 0;

            if (!empty($flink)) {

                try {

                    $facebook = getFacebook();
                    $fbuid    = getFacebook()->get_loggedin_user();

                } catch (Exception $e) {
                    common_log(LOG_WARNING,
                        'Problem getting Facebook client: ' .
                            $e->getMessage());
                }

                if ($fbuid > 0) {
                    return $fbuid;
                }
            }
        }

        return null;
    }

    function onStartPrimaryNav($action)
    {

        $user = common_current_user();

        if (!empty($user)) {

            $fbuid = $this->loggedIn();

            if (!empty($fbuid)) {

                /* Default FB silhouette pic for FB users who haven't
                   uploaded a profile pic yet. */

                $silhouetteUrl =
                    'http://static.ak.fbcdn.net/pics/q_silhouette.gif';

                $url = $this->getProfilePicURL($fbuid);

                $action->elementStart('li', array('id' => 'nav_fb'));

                $action->element('img', array('id' => 'fbc_profile-pic',
                    'src' => (!empty($url)) ? $url : $silhouetteUrl,
                    'alt' => 'Facebook Connect User',
                    'width' => '16'), '');

                $iconurl =  common_path('plugins/FBConnect/fbfavicon.ico');
                $action->element('img', array('id' => 'fb_favicon',
                    'src' => $iconurl));

                $action->elementEnd('li');

            }

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
            if (common_config('invite', 'enabled')) {
                $action->menuItem(common_local_url('invite'),
                    _('Invite'),
                    sprintf(_('Invite friends and colleagues to join you on %s'),
                    common_config('site', 'name')),
                    false, 'nav_invitecontact');
            }

            // Need to override the Logout link to make it do FB stuff
            if (!empty($fbuid)) {

                $logout_url = common_local_url('logout');
                $title =  _('Logout from the site');
                $text = _('Logout');

                $html = sprintf('<li id="nav_logout"><a href="%s" title="%s" ' .
                    'onclick="FB.Connect.logout(function() { goto_logout() })">%s</a></li>',
                    $logout_url, $title, $text);

                $action->raw($html);

             } else {
                 $action->menuItem(common_local_url('logout'),
                     _('Logout'), _('Logout from the site'), false, 'nav_logout');
             }
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

        return false;
    }

    function onStartShowLocalNavBlock($action)
    {
        $action_name   = get_class($action);

        $login_actions = array('LoginAction', 'RegisterAction',
            'OpenidloginAction', 'FBConnectLoginAction');

        if (in_array($action_name, $login_actions)) {
            $nav = new FBCLoginGroupNav($action);
            $nav->show();
            return false;
        }

        $connect_actions = array('SmssettingsAction', 'ImsettingsAction',
            'TwittersettingsAction', 'FBConnectSettingsAction');

        if (in_array($action_name, $connect_actions)) {
            $nav = new FBCSettingsNav($action);
            $nav->show();
            return false;
        }

        return true;
    }

    function onStartLogout($action)
    {
        $action->logout();
        $fbuid = $this->loggedIn();

        if (!empty($fbuid)) {
            try {
                $facebook = getFacebook();
                $facebook->expire_session();
            } catch (Exception $e) {
                common_log(LOG_WARNING, 'Could\'t logout of Facebook: ' .
                    $e->getMessage());
            }
        }

        return true;
    }

    function getProfilePicURL($fbuid)
    {

        $facebook = getFacebook();
        $url      = null;

        try {

            $fqry = 'SELECT pic_square FROM user WHERE uid = %s';

            $result = $facebook->api_client->fql_query(sprintf($fqry, $fbuid));

            if (!empty($result)) {
                $url = $result[0]['pic_square'];
            }

        } catch (Exception $e) {
            common_log(LOG_WARNING, "Facebook client failure requesting profile pic!");
        }

       return $url;

    }

}
