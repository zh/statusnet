<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base action for OAuth API endpoints
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

require_once INSTALLDIR . '/lib/apioauthstore.php';

/**
 * Base action for API OAuth enpoints.  Clean up the
 * the request, and possibly some other common things
 * here.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthAction extends Action
{
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
        self::cleanRequest();
    }

    static function cleanRequest()
    {
        // kill evil effects of magical slashing

        if (get_magic_quotes_gpc() == 1) {
            $_POST = array_map('stripslashes', $_POST);
            $_GET = array_map('stripslashes', $_GET);
        }

        // strip out the p param added in index.php

        // XXX: should we strip anything else?  Or alternatively
        // only allow a known list of params?

        unset($_GET['p']);
        unset($_POST['p']);
    }

    function getCallback($url, $params)
    {
        foreach ($params as $k => $v) {
            $url = $this->appendQueryVar($url,
                                         OAuthUtil::urlencode_rfc3986($k),
                                         OAuthUtil::urlencode_rfc3986($v));
        }

        return $url;
    }

    function appendQueryVar($url, $k, $v) {
        $url = preg_replace('/(.*)(\?|&)' . $k . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
        $url = substr($url, 0, -1);
        if (strpos($url, '?') === false) {
            return ($url . '?' . $k . '=' . $v);
        } else {
            return ($url . '&' . $k . '=' . $v);
        }
    }

}
