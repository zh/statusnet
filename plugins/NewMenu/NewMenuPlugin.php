<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Do a different menu layout
 *
 * PHP version 5
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
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Somewhat different menu navigation
 *
 * We have a new menu layout coming in StatusNet 1.0. This plugin gets
 * some of the new navigation in, although third-level menus aren't enabled.
 *
 * @category  NewMenu
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewMenuPlugin extends Plugin
{
    public $loadCSS = false;

    /**
     * Modify the default menu
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */

    function onStartPrimaryNav($action)
    {
        $user = common_current_user();

        if (!empty($user)) {
            $action->menuItem(common_local_url('all', 
                                               array('nickname' => $user->nickname)),
                              _m('Home'),
                              _m('Friends timeline'),
                              false,
                              'nav_home');
            $action->menuItem(common_local_url('showstream', 
                                               array('nickname' => $user->nickname)),
                              _m('Profile'),
                              _m('Your profile'),
                              false,
                              'nav_profile');
            $action->menuItem(common_local_url('public'),
                              _m('Public'),
                              _m('Everyone on this site'),
                              false,
                              'nav_public');
            $action->menuItem(common_local_url('profilesettings'),
                              _m('Settings'),
                              _m('Change your personal settings'),
                              false,
                              'nav_account');
            if ($user->hasRight(Right::CONFIGURESITE)) {
                $action->menuItem(common_local_url('siteadminpanel'),
                                  _m('Admin'), 
                                  _m('Site configuration'),
                                  false,
                                  'nav_admin');
            }
            $action->menuItem(common_local_url('logout'),
                              _m('Logout'), 
                              _m('Logout from the site'),
                              false,
                              'nav_logout');
        } else {
            $action->menuItem(common_local_url('public'),
                              _m('Public'),
                              _m('Everyone on this site'),
                              false,
                              'nav_public');
            $action->menuItem(common_local_url('login'),
                              _m('Login'), 
                              _m('Login to the site'),
                              false,
                              'nav_login');
        }

        if (!empty($user) || !common_config('site', 'private')) {
            $action->menuItem(common_local_url('noticesearch'),
                              _m('Search'),
                              _m('Search the site'),
                              false,
                              'nav_search');
        }

        Event::handle('EndPrimaryNav', array($action));

        return false;
    }

    function onStartPersonalGroupNav($menu)
    {
        $user = null;

        // FIXME: we should probably pass this in

        $action = $menu->action->trimmed('action');
        $nickname = $menu->action->trimmed('nickname');

        if ($nickname) {
            $user = User::staticGet('nickname', $nickname);
            $user_profile = $user->getProfile();
            $name = $user_profile->getBestName();
        } else {
            // @fixme can this happen? is this valid?
            $user_profile = false;
            $name = $nickname;
        }

        $menu->out->menuItem(common_local_url('all', array('nickname' =>
                                                           $nickname)),
                             _('Home'),
                             sprintf(_('%s and friends'), $name),
                             $action == 'all', 'nav_timeline_personal');
        $menu->out->menuItem(common_local_url('replies', array('nickname' =>
                                                               $nickname)),
                             _('Replies'),
                             sprintf(_('Replies to %s'), $name),
                             $action == 'replies', 'nav_timeline_replies');
        $menu->out->menuItem(common_local_url('showfavorites', array('nickname' =>
                                                                     $nickname)),
                             _('Favorites'),
                             sprintf(_('%s\'s favorite notices'), ($user_profile) ? $name : _('User')),
                             $action == 'showfavorites', 'nav_timeline_favorites');

        $cur = common_current_user();

        if ($cur && $cur->id == $user->id &&
            !common_config('singleuser', 'enabled')) {

            $menu->out->menuItem(common_local_url('inbox', array('nickname' =>
                                                                 $nickname)),
                                 _('Inbox'),
                                 _('Your incoming messages'),
                                 $action == 'inbox');
            $menu->out->menuItem(common_local_url('outbox', array('nickname' =>
                                                                  $nickname)),
                                 _('Outbox'),
                                 _('Your sent messages'),
                                 $action == 'outbox');
        }
        Event::handle('EndPersonalGroupNav', array($menu));
        return false;
    }

    function onStartSubGroupNav($menu)
    {
        $cur = common_current_user();
        $action = $menu->action->trimmed('action');

        $profile = $menu->user->getProfile();

        $menu->out->menuItem(common_local_url('showstream', array('nickname' =>
                                                                  $menu->user->nickname)),
                             _('Profile'),
                             (empty($profile)) ? $menu->user->nickname : $profile->getBestName(),
                             $action == 'showstream',
                             'nav_profile');
        $menu->out->menuItem(common_local_url('subscriptions',
                                              array('nickname' =>
                                                    $menu->user->nickname)),
                             _('Subscriptions'),
                             sprintf(_('People %s subscribes to'),
                                     $menu->user->nickname),
                             $action == 'subscriptions',
                             'nav_subscriptions');
        $menu->out->menuItem(common_local_url('subscribers',
                                              array('nickname' =>
                                                    $menu->user->nickname)),
                             _('Subscribers'),
                             sprintf(_('People subscribed to %s'),
                                     $menu->user->nickname),
                             $action == 'subscribers',
                             'nav_subscribers');
        $menu->out->menuItem(common_local_url('usergroups',
                                              array('nickname' =>
                                                    $menu->user->nickname)),
                             _('Groups'),
                             sprintf(_('Groups %s is a member of'),
                                     $menu->user->nickname),
                             $action == 'usergroups',
                             'nav_usergroups');
        if (common_config('invite', 'enabled') && !is_null($cur) && $menu->user->id === $cur->id) {
            $menu->out->menuItem(common_local_url('invite'),
                                 _('Invite'),
                                 sprintf(_('Invite friends and colleagues to join you on %s'),
                                         common_config('site', 'name')),
                                 $action == 'invite',
                                 'nav_invite');
        }

        Event::handle('EndSubGroupNav', array($menu));
        return false;
    }

    function onStartShowLocalNavBlock($action)
    {
        $actionName = $action->trimmed('action');
        
        if ($actionName == 'showstream') {
            $action->elementStart('dl', array('id' => 'site_nav_local_views'));
            // TRANS: DT element for local views block. String is hidden in default CSS.
            $action->element('dt', null, _('Local views'));
            $action->elementStart('dd');
            $nav = new SubGroupNav($action, $action->user);
            $nav->show();
            $action->elementEnd('dd');
            $action->elementEnd('dl');
            Event::handle('EndShowLocalNavBlock', array($action));
            return false;
        }

        return true;
    }

    function onStartAccountSettingsNav($action)
    {
        $this->_settingsMenu($action);
        return false;
    }

    function onStartConnectSettingsNav($action)
    {
        $this->_settingsMenu($action);
        return false;
    }

    private function _settingsMenu($action)
    {
        $actionName = $action->trimmed('action');

        $action->menuItem(common_local_url('profilesettings'),
                          _('Profile'),
                          _('Change your profile settings'),
                          $actionName == 'profilesettings');

        $action->menuItem(common_local_url('avatarsettings'),
                          _('Avatar'),
                          _('Upload an avatar'),
                          $actionName == 'avatarsettings');

        $action->menuItem(common_local_url('passwordsettings'),
                          _('Password'),
                          _('Change your password'),
                          $actionName == 'passwordsettings');

        $action->menuItem(common_local_url('emailsettings'),
                          _('Email'),
                          _('Change email handling'),
                          $actionName == 'emailsettings');

        $action->menuItem(common_local_url('userdesignsettings'),
                          _('Design'),
                          _('Design your profile'),
                          $actionName == 'userdesignsettings');

        $action->menuItem(common_local_url('othersettings'),
                          _('Other'),
                          _('Other options'),
                          $actionName == 'othersettings');

        Event::handle('EndAccountSettingsNav', array($action));
        
        if (common_config('xmpp', 'enabled')) {
            $action->menuItem(common_local_url('imsettings'),
                              _m('IM'),
                              _('Updates by instant messenger (IM)'),
                              $actionName == 'imsettings');
        }

        if (common_config('sms', 'enabled')) {
            $action->menuItem(common_local_url('smssettings'),
                              _m('SMS'),
                              _('Updates by SMS'),
                              $actionName == 'smssettings');
        }

        $action->menuItem(common_local_url('oauthconnectionssettings'),
                          _('Connections'),
                          _('Authorized connected applications'),
                          $actionName == 'oauthconnectionsettings');

        Event::handle('EndConnectSettingsNav', array($action));
    }

    function onEndShowStyles($action)
    {
        if (($this->loadCSS ||
             in_array(common_config('site', 'theme'),
                      array('default', 'identica', 'h4ck3r'))) &&
            ($action instanceof AccountSettingsAction ||
             $action instanceof ConnectSettingsAction)) {
            $action->cssLink($this->path('newmenu.css'));
        }
        return true;
    }

    function onStartAddressData($action)
    {
        if (common_config('singleuser', 'enabled')) {
            $user = User::singleUser();
            $url = common_local_url('showstream',
                                    array('nickname' => $user->nickname));
        } else if (common_logged_in()) {
            $cur = common_current_user();
            $url = common_local_url('all', array('nickname' => $cur->nickname));
        } else {
            $url = common_local_url('public');
        }

        $action->elementStart('a', array('class' => 'url home bookmark',
                                         'href' => $url));

        if (StatusNet::isHTTPS()) {
            $logoUrl = common_config('site', 'ssllogo');
            if (empty($logoUrl)) {
                // if logo is an uploaded file, try to fall back to HTTPS file URL
                $httpUrl = common_config('site', 'logo');
                if (!empty($httpUrl)) {
                    $f = File::staticGet('url', $httpUrl);
                    if (!empty($f) && !empty($f->filename)) {
                        // this will handle the HTTPS case
                        $logoUrl = File::url($f->filename);
                    }
                }
            }
        } else {
            $logoUrl = common_config('site', 'logo');
        }

        if (empty($logoUrl) && file_exists(Theme::file('logo.png'))) {
            // This should handle the HTTPS case internally
            $logoUrl = Theme::path('logo.png');
        }

        if (!empty($logoUrl)) {
            $action->element('img', array('class' => 'logo photo',
                                          'src' => $logoUrl,
                                          'alt' => common_config('site', 'name')));
        }

        $action->text(' ');
        $action->element('span', array('class' => 'fn org'), common_config('site', 'name'));
        $action->elementEnd('a');

        Event::handle('EndAddressData', array($action));
        return false;
    }

    /**
     * Return version information for this plugin
     *
     * @param array &$versions Version info; add to this array
     *
     * @return boolean hook value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'NewMenu',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:NewMenu',
                            'description' =>
                            _m('A preview of the new menu '.
                               'layout in StatusNet 1.0.'));
        return true;
    }
}
