<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin for single-sign-in (SSO) with Facebook
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
 * @category  Pugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define("FACEBOOK_SERVICE", 2);

/**
 * Main class for Facebook single-sign-on plugin
 *
 *
 * Simple plugins can be implemented as a single module. Others are more complex
 * and require additional modules; these should use their own directory, like
 * 'local/plugins/{$name}/'. All files related to the plugin, including images,
 * JavaScript, CSS, external libraries or PHP modules should go in the plugin
 * directory.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class FacebookSSOPlugin extends Plugin
{
    public $appId    = null; // Facebook application ID
    public $secret   = null; // Facebook application secret
    public $facebook = null; // Facebook application instance
    public $dir      = null; // Facebook SSO plugin dir

    /**
     * Initializer for this plugin
     *
     * Plugins overload this method to do any initialization they need,
     * like connecting to remote servers or creating paths or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {
        common_debug("XXXXXXXXXXXX " . $this->appId);
        // Check defaults and configuration for application ID and secret
        if (empty($this->appId)) {
            $this->appId = common_config('facebook', 'appid');
        }

        if (empty($this->secret)) {
            $this->secret = common_config('facebook', 'secret');
        }

        if (empty($this->facebook)) {
            common_debug('instantiating facebook obj');
            $this->facebook = new Facebook(
                array(
                    'appId'  => $this->appId,
                    'secret' => $this->secret,
                    'cookie' => true
                )
            );
        }
        
        common_debug("FACEBOOK = " . var_export($this->facebook, true));

        return true;
    }

    /**
     * Cleanup for this plugin
     *
     * Plugins overload this method to do any cleanup they need,
     * like disconnecting from remote servers or deleting temp files or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function cleanup()
    {
        return true;
    }

    /**
     * Load related modules when needed
     *
     * Most non-trivial plugins will require extra modules to do their work. Typically
     * these include data classes, action classes, widget classes, or external libraries.
     *
     * This method receives a class name and loads the PHP file related to that class. By
     * tradition, action classes typically have files named for the action, all lower-case.
     * Data classes are in files with the data class name, initial letter capitalized.
     *
     * Note that this method will be called for *all* overloaded classes, not just ones
     * in this plugin! So, make sure to return true by default to let other plugins, and
     * the core code, get a chance.
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {

        $dir = dirname(__FILE__);

        //common_debug("class = " . $cls);

        switch ($cls)
        {
        case 'Facebook':
            include_once $dir . '/extlib/facebook.php';
            return false;
        case 'FacebookloginAction':
        case 'FacebookregisterAction':
        case 'FacebookadminpanelAction':
        case 'FacebooksettingsAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        default:
            return true;
        }

    }

    /**
     * Map URLs to actions
     *
     * This event handler lets the plugin map URLs on the site to actions (and
     * thus an action handler class). Note that the action handler class for an
     * action will be named 'FoobarAction', where action = 'foobar'. The class
     * must be loaded in the onAutoload() method.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        // Always add the admin panel route
        $m->connect('admin/facebook', array('action' => 'facebookadminpanel'));

        // Only add these routes if an application has been setup on
        // Facebook for the plugin to use.
        if ($this->hasApplication()) {

            $m->connect(
                'main/facebooklogin',
                array('action' => 'facebooklogin')
            );
            $m->connect(
                'main/facebookregister',
                array('action' => 'facebookregister')
            );

            $m->connect(
                'settings/facebook',
                array('action' => 'facebooksettings')
            );
            
        }

        return true;
    }

    /*
     * Add a login tab for Facebook, but only if there's a Facebook
     * application defined for the plugin to use.
     *
     * @param Action &action the current action
     *
     * @return void
     */
    function onEndLoginGroupNav(&$action)
    {
        $action_name = $action->trimmed('action');

        if ($this->hasApplication()) {

            $action->menuItem(
                common_local_url('facebooklogin'),
                _m('MENU', 'Facebook'),
                // TRANS: Tooltip for menu item "Facebook".
                _m('Login or register using Facebook'),
               'facebooklogin' === $action_name
            );
        }

        return true;
    }

    /**
     * Add a Facebook tab to the admin panels
     *
     * @param Widget $nav Admin panel nav
     *
     * @return boolean hook value
     */
    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('facebook')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(
                common_local_url('facebookadminpanel'),
                // TRANS: Menu item.
                _m('MENU','Facebook'),
                // TRANS: Tooltip for menu item "Facebook".
                _m('Facebook integration configuration'),
                $action_name == 'facebookadminpanel',
                'nav_facebook_admin_panel'
            );
        }

        return true;
    }

    /*
     * Add a tab for managing Facebook Connect settings
     *
     * @param Action &action the current action
     *
     * @return void
     */
    function onEndConnectSettingsNav(&$action)
    {
        if ($this->hasApplication()) {
            $action_name = $action->trimmed('action');

            $action->menuItem(common_local_url('facebooksettings'),
                              // @todo CHECKME: Should be 'Facebook Connect'?
                              // TRANS: Menu item tab.
                              _m('MENU','Facebook'),
                              // TRANS: Tooltip for menu item "Facebook".
                              _m('Facebook Connect Settings'),
                              $action_name === 'facebooksettings');
        }

        return true;
    }

    /*
     * Is there a Facebook application for the plugin to use?
     */
    function hasApplication()
    {
        if (!empty($this->appId) && !empty($this->secret)) {
            return true;
        } else {
            return false;
        }
    }

    function onStartShowHeader($action)
    {
        // output <div id="fb-root"></div> as close to <body> as possible
        $action->element('div', array('id' => 'fb-root'));

        $session = $this->facebook->getSession();
        $dir = dirname(__FILE__);

        // XXX: minify this
        $script = <<<ENDOFSCRIPT
        window.fbAsyncInit = function() {

    FB.init({
      appId   : %s,
      session : %s,   // don't refetch the session when PHP already has it
      status  : true, // check login status
      cookie  : true, // enable cookies to allow the server to access the session
      xfbml   : true  // parse XFBML
    });

    // whenever the user logs in, refresh the page
    FB.Event.subscribe(
        'auth.login',
        function() {
            window.location.reload();
        }
    );
};

(function() {
    var e = document.createElement('script');
    e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
    e.async = true;
    document.getElementById('fb-root').appendChild(e);
}());
ENDOFSCRIPT;

        $action->inlineScript(
            sprintf($script,
                json_encode($this->appId),
                json_encode($this->session)
            )
        );
        
        return true;
    }

    function onStartHtmlElement($action, $attrs) {
        $attrs = array_merge($attrs, array('xmlns:fb' => 'http://www.facebook.com/2008/fbml'));
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Facebook Single-Sign-On',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:FacebookSSO',
                            'rawdescription' =>
                            _m('A plugin for single-sign-on with Facebook.'));
        return true;
    }
}
