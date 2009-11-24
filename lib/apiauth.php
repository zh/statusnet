<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for API actions that require authentication
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
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Brenda Wallace <shiny@cpan.org>
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    mEDI <medi@milaro.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net> 
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/api.php';

/**
 * Actions extending this class will require auth
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiAuthAction extends ApiAction
{

    var $auth_user = null;

    /**
     * Take arguments for running, and output basic auth header if needed
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        if ($this->requiresAuth()) {
            $this->checkBasicAuthUser();
        }

        return true;
    }

    /**
     * Does this API resource require authentication?
     *
     * @return boolean true
     */

    function requiresAuth()
    {
        return true;
    }

    /**
     * Check for a user specified via HTTP basic auth. If there isn't
     * one, try to get one by outputting the basic auth header.
     *
     * @return boolean true or false
     */

    function checkBasicAuthUser()
    {
        $this->basicAuthProcessHeader();

        $realm = common_config('site', 'name') . ' API';

        if (!isset($this->auth_user)) {
            header('WWW-Authenticate: Basic realm="' . $realm . '"');

            // show error if the user clicks 'cancel'

            $this->showBasicAuthError();
            exit;

        } else {
            $nickname = $this->auth_user;
            $password = $this->auth_pw;
            $user = common_check_user($nickname, $password);
            if (Event::handle('StartSetApiUser', array(&$user))) {
                $this->auth_user = $user;
                Event::handle('EndSetApiUser', array($user));
            }

            if (empty($this->auth_user)) {

                // basic authentication failed

                list($proxy, $ip) = common_client_ip();
                common_log(
                    LOG_WARNING,
                    'Failed API auth attempt, nickname = ' .
                    "$nickname, proxy = $proxy, ip = $ip."
                );
                $this->showBasicAuthError();
                exit;
            }
        }
        return true;
    }

    /**
     * Read the HTTP headers and set the auth user.  Decodes HTTP_AUTHORIZATION
     * param to support basic auth when PHP is running in CGI mode.
     *
     * @return void
     */

    function basicAuthProcessHeader()
    {
        if (isset($_SERVER['AUTHORIZATION'])
            || isset($_SERVER['HTTP_AUTHORIZATION'])
        ) {
                $authorization_header = isset($_SERVER['HTTP_AUTHORIZATION'])
                ? $_SERVER['HTTP_AUTHORIZATION'] : $_SERVER['AUTHORIZATION'];
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $this->auth_user = $_SERVER['PHP_AUTH_USER'];
            $this->auth_pw = $_SERVER['PHP_AUTH_PW'];
        } elseif (isset($authorization_header)
            && strstr(substr($authorization_header, 0, 5), 'Basic')) {

            // decode the HTTP_AUTHORIZATION header on php-cgi server self
            // on fcgid server the header name is AUTHORIZATION

            $auth_hash = base64_decode(substr($authorization_header, 6));
            list($this->auth_user, $this->auth_pw) = explode(':', $auth_hash);

            // set all to null on a empty basic auth request

            if ($this->auth_user == "") {
                $this->auth_user = null;
                $this->auth_pw = null;
            }
        } else {
            $this->auth_user = null;
            $this->auth_pw = null;
        }
    }

    /**
     * Output an authentication error message.  Use XML or JSON if one
     * of those formats is specified, otherwise output plain text
     *
     * @return void
     */

    function showBasicAuthError()
    {
        header('HTTP/1.1 401 Unauthorized');
        $msg = 'Could not authenticate you.';

        if ($this->format == 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            $this->startXML();
            $this->elementStart('hash');
            $this->element('error', null, $msg);
            $this->element('request', null, $_SERVER['REQUEST_URI']);
            $this->elementEnd('hash');
            $this->endXML();
        } elseif ($this->format == 'json') {
            header('Content-Type: application/json; charset=utf-8');
            $error_array = array('error' => $msg,
                                 'request' => $_SERVER['REQUEST_URI']);
            print(json_encode($error_array));
        } else {
            header('Content-type: text/plain');
            print "$msg\n";
        }
    }

}
