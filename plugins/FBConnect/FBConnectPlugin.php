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
     }

    // Add in xmlns:fb
    function onStartShowHTML($action)
    {
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

        $login_url = common_local_url('FBConnectAuth');
        $logout_url = common_local_url('logout');

        $html = sprintf('<script type="text/javascript">FB.init("%s", "%s/xd_receiver.htm");

                            function goto_login() {
                                window.location = "%s";
                            }

                            function goto_logout() {
                                window.location = "%s";
                            }

                         </script>', $apikey, $plugin_path, $login_url, $logout_url);


        $action->raw($html);
    }

    function onStartPrimaryNav($action)
    {
        $user = common_current_user();

        if ($user) {

            $flink = Foreign_link::getByUserId($user->id, FACEBOOK_CONNECT_SERVICE);

            if ($flink) {

                $facebook = getFacebook();

                if ($facebook->api_client->users_isAppUser($flink->foreign_id) ||
                    $facebook->api_client->added) {

                    // XXX: We need to replace this with a proper mini-icon and only after
                    // checing the FB Connect JavaScript lib method to see what the Connect
                    // status is. Checking Connect status looks to be impossible with the
                    // PHP client.

                    $action->elementStart('li');
                    $action->elementStart('fb:profile-pic', array('uid' => $flink->foreign_id,
                        'facebook-logo' => 'true',
                        'linked' => 'false',
                        'width' => 32,
                        'height' => 32));
                    $action->elementEnd('fb:profile-pic');
                    $action->elementEnd('li');
                }

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
                 'onclick="FB.Connect.logout(function() { goto_logout() })">%s</a></li>',
                    $logout_url, $title, $text);

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

        return false;
    }

    function onStartShowLocalNavBlock($action)
    {
        $action_name = get_class($action);

        $login_actions = array('LoginAction', 'RegisterAction',
            'OpenidloginAction', 'FacebookStart');

        if (in_array($action_name, $login_actions)) {
            $nav = new FBCLoginGroupNav($action);
            $nav->show();
            return false;
        }

        $connect_actions = array('SmssettingsAction',
            'TwittersettingsAction', 'FBConnectSettingsAction');

        if (in_array($action_name, $connect_actions)) {
            $nav = new FBCSettingsNav($action);
            $nav->show();
            return false;
        }

        return true;
    }

}


