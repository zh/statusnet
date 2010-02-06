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

    private $_nicknamePatterns = array();
    private $_urlPatterns  = array();

    function initialize()
    {
        $this->_nicknamePatterns = array_merge($this->nicknames,
                                               $this->_configArray('blacklist', 'nicknames'));

        $this->_urlPatterns = array_merge($this->urls,
                                          $this->_configArray('blacklist', 'urls'));
    }

    function _configArray($section, $setting)
    {
        $config = common_config($section, $setting);

        if (empty($config)) {
            return array();
        } else if (is_array($config)) {
            return $config;
        } else if (is_string($config)) {
            return explode("\t", $config);
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
            if (preg_match("/$pattern/", $nickname)) {
                return false;
            }
        }

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Blacklist',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Blacklist',
                            'description' =>
                            _m('Keep a blacklist of forbidden nickname and URL patterns.'));
        return true;
    }
}
