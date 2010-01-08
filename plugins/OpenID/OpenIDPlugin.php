<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin for OpenID authentication and identity
 *
 * This class enables consumer support for OpenID, the distributed authentication
 * and identity system.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @link     http://openid.net/
 */

class OpenIDPlugin extends Plugin
{
    /**
     * Initializer for the plugin.
     */

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Add OpenID-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @return boolean hook return
     */

    function onStartInitializeRouter($m)
    {
        $m->connect('main/openid', array('action' => 'openidlogin'));
        $m->connect('main/openidtrust', array('action' => 'openidtrust'));
        $m->connect('settings/openid', array('action' => 'openidsettings'));
        $m->connect('index.php?action=finishopenidlogin', array('action' => 'finishopenidlogin'));
        $m->connect('index.php?action=finishaddopenid', array('action' => 'finishaddopenid'));
        $m->connect('main/openidserver', array('action' => 'openidserver'));

        return true;
    }

    function onEndPublicXRDS($action, &$xrdsOutputter)
    {
        $xrdsOutputter->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $xrdsOutputter->element('Type', null, 'xri://$xrds*simple');
        //consumer
        foreach (array('finishopenidlogin', 'finishaddopenid') as $finish) {
            $xrdsOutputter->showXrdsService(Auth_OpenID_RP_RETURN_TO_URL_TYPE,
                                common_local_url($finish));
        }
        //provider
        $xrdsOutputter->showXrdsService('http://specs.openid.net/auth/2.0/server',
                            common_local_url('openidserver'),
                            null,
                            null,
                            'http://specs.openid.net/auth/2.0/identifier_select');
        $xrdsOutputter->elementEnd('XRD');
    }

    function onEndUserXRDS($action, &$xrdsOutputter)
    {
        $xrdsOutputter->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xml:id' => 'openid',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $xrdsOutputter->element('Type', null, 'xri://$xrds*simple');

        //consumer
        $xrdsOutputter->showXrdsService('http://specs.openid.net/auth/2.0/return_to',
                            common_local_url('finishopenidlogin'));

        //provider
        $xrdsOutputter->showXrdsService('http://specs.openid.net/auth/2.0/signon',
                            common_local_url('openidserver'),
                            null,
                            null,
                            common_profile_url($action->user->nickname));
        $xrdsOutputter->elementEnd('XRD');
    }

    function onEndLoginGroupNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('openidlogin'),
                          _m('OpenID'),
                          _m('Login or register with OpenID'),
                          $action_name === 'openidlogin');

        return true;
    }

    function onEndAccountSettingsNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('openidsettings'),
                          _m('OpenID'),
                          _m('Add or remove OpenIDs'),
                          $action_name === 'openidsettings');

        return true;
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'OpenidloginAction':
         case 'FinishopenidloginAction':
         case 'FinishaddopenidAction':
         case 'XrdsAction':
         case 'PublicxrdsAction':
         case 'OpenidsettingsAction':
         case 'OpenidserverAction':
         case 'OpenidtrustAction':
            require_once(INSTALLDIR.'/plugins/OpenID/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
            return false;
         case 'User_openid':
            require_once(INSTALLDIR.'/plugins/OpenID/User_openid.php');
            return false;
         case 'User_openid_trustroot':
            require_once(INSTALLDIR.'/plugins/OpenID/User_openid_trustroot.php');
            return false;
         default:
            return true;
        }
    }

    function onSensitiveAction($action, &$ssl)
    {
        switch ($action)
        {
         case 'finishopenidlogin':
         case 'finishaddopenid':
            $ssl = true;
            return false;
         default:
            return true;
        }
    }

    function onLoginAction($action, &$login)
    {
        switch ($action)
        {
         case 'openidlogin':
         case 'finishopenidlogin':
         case 'openidserver':
            $login = true;
            return false;
         default:
            return true;
        }
    }

    /**
     * We include a <meta> element linking to the publicxrds page, for OpenID
     * client-side authentication.
     *
     * @return void
     */

    function onEndShowHeadElements($action)
    {
        if($action instanceof ShowstreamAction){
            $action->element('link', array('rel' => 'openid2.provider',
                                           'href' => common_local_url('openidserver')));
            $action->element('link', array('rel' => 'openid2.local_id',
                                           'href' => $action->profile->profileurl));
            $action->element('link', array('rel' => 'openid.server',
                                           'href' => common_local_url('openidserver')));
            $action->element('link', array('rel' => 'openid.delegate',
                                           'href' => $action->profile->profileurl));
        }
        return true;
    }

    /**
     * Redirect to OpenID login if they have an OpenID
     *
     * @return boolean whether to continue
     */

    function onRedirectToLogin($action, $user)
    {
        if (!empty($user) && User_openid::hasOpenID($user->id)) {
            common_redirect(common_local_url('openidlogin'), 303);
            return false;
        }
        return true;
    }

    function onEndShowPageNotice($action)
    {
        $name = $action->trimmed('action');

        switch ($name)
        {
         case 'register':
            $instr = '(Have an [OpenID](http://openid.net/)? ' .
              'Try our [OpenID registration]'.
              '(%%action.openidlogin%%)!)';
            break;
         case 'login':
            $instr = '(Have an [OpenID](http://openid.net/)? ' .
              'Try our [OpenID login]'.
              '(%%action.openidlogin%%)!)';
            break;
         default:
            return true;
        }

        $output = common_markup_to_html($instr);
        $action->raw($output);
        return true;
    }

    function onStartLoadDoc(&$title, &$output)
    {
        if ($title == 'openid')
        {
            $filename = INSTALLDIR.'/plugins/OpenID/doc-src/openid';

            $c = file_get_contents($filename);
            $output = common_markup_to_html($c);
            return false; // success!
        }

        return true;
    }

    function onEndLoadDoc($title, &$output)
    {
        if ($title == 'help')
        {
            $menuitem = '* [OpenID](%%doc.openid%%) - what OpenID is and how to use it with this service';

            $output .= common_markup_to_html($menuitem);
        }

        return true;
    }

    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('user_openid',
                             array(new ColumnDef('canonical', 'varchar',
                                                 '255', false, 'PRI'),
                                   new ColumnDef('display', 'varchar',
                                                 '255', false, 'UNI'),
                                   new ColumnDef('user_id', 'integer',
                                                 null, false, 'MUL'),
                                   new ColumnDef('created', 'datetime',
                                                 null, false),
                                   new ColumnDef('modified', 'timestamp')));
        $schema->ensureTable('user_openid_trustroot',
                             array(new ColumnDef('trustroot', 'varchar',
                                                 '255', false, 'PRI'),
                                   new ColumnDef('user_id', 'integer',
                                                 null, false, 'PRI'),
                                   new ColumnDef('created', 'datetime',
                                                 null, false),
                                   new ColumnDef('modified', 'timestamp')));
        return true;
    }

    function onUserDeleteRelated($user, &$tables)
    {
        $tables[] = 'User_openid';
        $tables[] = 'User_openid_trustroot';
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'OpenID',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou, Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:OpenID',
                            'rawdescription' =>
                            _m('Use <a href="http://openid.net/">OpenID</a> to login to the site.'));
        return true;
    }
}
