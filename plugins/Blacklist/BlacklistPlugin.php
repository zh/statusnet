<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to prevent use of nicknames or URLs on a blacklist
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to prevent use of nicknames or URLs on a blacklist
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class BlacklistPlugin extends Plugin
{
    const VERSION = STATUSNET_VERSION;

    public $nicknames = array();
    public $urls      = array();
    public $canAdmin  = true;

    private $_nicknamePatterns = array();
    private $_urlPatterns      = array();

    /**
     * Initialize the plugin
     *
     * @return void
     */

    function initialize()
    {
        $confNicknames = $this->_configArray('blacklist', 'nicknames')

        $this->_nicknamePatterns = array_merge($this->nicknames,
                                               $confNicknames);

        $confURLs = $this->_configArray('blacklist', 'urls')

        $this->_urlPatterns = array_merge($this->urls,
                                          $confURLs);
    }

    /**
     * Retrieve an array from configuration
     *
     * Carefully checks a section.
     *
     * @param string $section Configuration section
     * @param string $setting Configuration setting
     *
     * @return array configuration values
     */

    function _configArray($section, $setting)
    {
        $config = common_config($section, $setting);

        if (empty($config)) {
            return array();
        } else if (is_array($config)) {
            return $config;
        } else if (is_string($config)) {
            return explode("\r\n", $config);
        } else {
            throw new Exception("Unknown data type for config $section + $setting");
        }
    }

    /**
     * Hook registration to prevent blacklisted homepages or nicknames
     *
     * Throws an exception if there's a blacklisted homepage or nickname.
     *
     * @param Action $action Action being called (usually register)
     *
     * @return boolean hook value
     */

    function onStartRegistrationTry($action)
    {
        $homepage = strtolower($action->trimmed('homepage'));

        if (!empty($homepage)) {
            if (!$this->_checkUrl($homepage)) {
                $msg = sprintf(_m("You may not register with homepage '%s'"),
                               $homepage);
                throw new ClientException($msg);
            }
        }

        $nickname = strtolower($action->trimmed('nickname'));

        if (!empty($nickname)) {
            if (!$this->_checkNickname($nickname)) {
                $msg = sprintf(_m("You may not register with nickname '%s'"),
                               $nickname);
                throw new ClientException($msg);
            }
        }

        return true;
    }

    /**
     * Hook profile update to prevent blacklisted homepages or nicknames
     *
     * Throws an exception if there's a blacklisted homepage or nickname.
     *
     * @param Action $action Action being called (usually register)
     *
     * @return boolean hook value
     */

    function onStartProfileSaveForm($action)
    {
        $homepage = strtolower($action->trimmed('homepage'));

        if (!empty($homepage)) {
            if (!$this->_checkUrl($homepage)) {
                $msg = sprintf(_m("You may not use homepage '%s'"),
                               $homepage);
                throw new ClientException($msg);
            }
        }

        $nickname = strtolower($action->trimmed('nickname'));

        if (!empty($nickname)) {
            if (!$this->_checkNickname($nickname)) {
                $msg = sprintf(_m("You may not use nickname '%s'"),
                               $nickname);
                throw new ClientException($msg);
            }
        }

        return true;
    }

    /**
     * Hook notice save to prevent blacklisted urls
     *
     * Throws an exception if there's a blacklisted url in the content.
     *
     * @param Notice &$notice Notice being saved
     *
     * @return boolean hook value
     */

    function onStartNoticeSave(&$notice)
    {
        common_replace_urls_callback($notice->content,
                                     array($this, 'checkNoticeUrl'));
        return true;
    }

    /**
     * Helper callback for notice save
     *
     * Throws an exception if there's a blacklisted url in the content.
     *
     * @param string $url URL in the notice content
     *
     * @return boolean hook value
     */

    function checkNoticeUrl($url)
    {
        // It comes in special'd, so we unspecial it
        // before comparing against patterns

        $url = htmlspecialchars_decode($url);

        if (!$this->_checkUrl($url)) {
            $msg = sprintf(_m("You may not use url '%s' in notices"),
                           $url);
            throw new ClientException($msg);
        }

        return $url;
    }

    /**
     * Helper for checking URLs
     *
     * Checks an URL against our patterns for a match.
     *
     * @param string $url URL to check
     *
     * @return boolean true means it's OK, false means it's bad
     */

    private function _checkUrl($url)
    {
        foreach ($this->_urlPatterns as $pattern) {
            common_debug("Checking $url against $pattern");
            if (preg_match("/$pattern/", $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Helper for checking nicknames
     *
     * Checks a nickname against our patterns for a match.
     *
     * @param string $nickname nickname to check
     *
     * @return boolean true means it's OK, false means it's bad
     */

    private function _checkNickname($nickname)
    {
        foreach ($this->_nicknamePatterns as $pattern) {
            common_debug("Checking $nickname against $pattern");
            if (preg_match("/$pattern/", $nickname)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add our actions to the URL router
     *
     * @param Net_URL_Mapper $m URL mapper for this hit
     *
     * @return boolean hook return
     */

    function onRouterInitialized($m)
    {
        $m->connect('admin/blacklist', array('action' => 'blacklistadminpanel'));
        return true;
    }

    /**
     * Auto-load our classes if called
     *
     * @param string $cls Class to load
     *
     * @return boolean hook return
     */

    function onAutoload($cls)
    {
        switch (strtolower($cls))
        {
        case 'blacklistadminpanelaction':
            $base = strtolower(mb_substr($cls, 0, -6));
            include_once INSTALLDIR.'/plugins/Blacklist/'.$base.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version blocks
     *
     * @return boolean hook value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Blacklist',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' =>
                            'http://status.net/wiki/Plugin:Blacklist',
                            'description' =>
                            _m('Keep a blacklist of forbidden nickname '.
                               'and URL patterns.'));
        return true;
    }

    /**
     * Determines if our admin panel can be shown
     *
     * @param string  $name  name of the admin panel
     * @param boolean &$isOK result
     *
     * @return boolean hook value
     */

    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'blacklist') {
            $isOK = $this->canAdmin;
            return false;
        }

        return true;
    }

    /**
     * Add our tab to the admin panel
     *
     * @param Widget $nav Admin panel nav
     *
     * @return boolean hook value
     */

    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('blacklist')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(common_local_url('blacklistadminpanel'),
                                _('Blacklist'),
                                _('Blacklist configuration'),
                                $action_name == 'blacklistadminpanel',
                                'nav_blacklist_admin_panel');
        }

        return true;
    }
}
