<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility for doing HTTP-related things
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Response.php';

/**
 * Useful structure for HTTP responses
 *
 * We make HTTP calls in several places, and we have several different
 * ways of doing them. This class hides the specifics of what underlying
 * library (curl or PHP-HTTP or whatever) that's used.
 *
 * This extends the HTTP_Request2_Response class with methods to get info
 * about any followed redirects.
 *
 * @category HTTP
 * @package StatusNet
 * @author Evan Prodromou <evan@status.net>
 * @author Brion Vibber <brion@status.net>
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link http://status.net/
 */
class HTTPResponse extends HTTP_Request2_Response
{
    function __construct(HTTP_Request2_Response $response, $url, $redirects=0)
    {
        foreach (get_object_vars($response) as $key => $val) {
            $this->$key = $val;
        }
        $this->url = strval($url);
        $this->redirectCount = intval($redirects);
    }

    /**
     * Get the count of redirects that have been followed, if any.
     * @return int
     */
    function getRedirectCount() {
        return $this->redirectCount;
    }

    /**
     * Gets the final target URL, after any redirects have been followed.
     * @return string URL
     */
    function getUrl() {
        return $this->url;
    }
}

/**
 * Utility class for doing HTTP client stuff
 *
 * We make HTTP calls in several places, and we have several different
 * ways of doing them. This class hides the specifics of what underlying
 * library (curl or PHP-HTTP or whatever) that's used.
 *
 * This extends the PEAR HTTP_Request2 package:
 * - sends StatusNet-specific User-Agent header
 * - 'follow_redirects' config option, defaulting off
 * - 'max_redirs' config option, defaulting to 10
 * - extended response class adds getRedirectCount() and getUrl() methods
 * - get() and post() convenience functions return body content directly
 *
 * @category HTTP
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class HTTPClient extends HTTP_Request2
{

    function __construct($url=null, $method=self::METHOD_GET, $config=array())
    {
        $this->config['max_redirs'] = 10;
        $this->config['follow_redirects'] = false;
        parent::__construct($url, $method, $config);
        $this->setHeader('User-Agent', $this->userAgent());
    }

    /**
     * Convenience function to run a get request and return the response body.
     * Use when you don't need to get into details of the response.
     *
     * @return mixed string on success, false on failure
     */
    function get()
    {
        $this->setMethod(self::METHOD_GET);
        return $this->doRequest();
    }

    /**
     * Convenience function to post form data and return the response body.
     * Use when you don't need to get into details of the response.
     *
     * @param array associative array of form data to submit
     * @return mixed string on success, false on failure
     */
    public function post($data=array())
    {
        $this->setMethod(self::METHOD_POST);
        if ($data) {
            $this->addPostParameter($data);
        }
        return $this->doRequest();
    }

    /**
     * @return mixed string on success, false on failure
     */
    protected function doRequest()
    {
        try {
            $response = $this->send();
            $code = $response->getStatus();
            if (($code < 200) || ($code >= 400)) {
                return false;
            }
            return $response->getBody();
        } catch (HTTP_Request2_Exception $e) {
            $this->log(LOG_ERR, $e->getMessage());
            return false;
        }
    }
    
    protected function log($level, $detail) {
        $method = $this->getMethod();
        $url = $this->getUrl();
        common_log($level, __CLASS__ . ": HTTP $method $url - $detail");
    }

    /**
     * Pulls up StatusNet's customized user-agent string, so services
     * we hit can track down the responsible software.
     */
    function userAgent()
    {
        return "StatusNet/".STATUSNET_VERSION." (".STATUSNET_CODENAME.")";
    }

    function send()
    {
        $maxRedirs = intval($this->config['max_redirs']);
        if (empty($this->config['follow_redirects'])) {
            $maxRedirs = 0;
        }
        $redirs = 0;
        do {
            try {
                $response = parent::send();
            } catch (HTTP_Request2_Exception $e) {
                $this->log(LOG_ERR, $e->getMessage());
                throw $e;
            }
            $code = $response->getStatus();
            if ($code >= 200 && $code < 300) {
                $reason = $response->getReasonPhrase();
                $this->log(LOG_INFO, "$code $reason");
            } elseif ($code >= 300 && $code < 400) {
                $url = $this->getUrl();
                $target = $response->getHeader('Location');
                
                if (++$redirs >= $maxRedirs) {
                    common_log(LOG_ERR, __CLASS__ . ": Too many redirects: skipping $code redirect from $url to $target");
                    break;
                }
                try {
                    $this->setUrl($target);
                    $this->setHeader('Referer', $url);
                    common_log(LOG_INFO, __CLASS__ . ": Following $code redirect from $url to $target");
                    continue;
                } catch (HTTP_Request2_Exception $e) {
                    common_log(LOG_ERR, __CLASS__ . ": Invalid $code redirect from $url to $target");
                }
            } else {
                $reason = $response->getReasonPhrase();
                $this->log(LOG_ERR, "$code $reason");
            }
            break;
        } while ($maxRedirs);
        return new HTTPResponse($response, $this->getUrl(), $redirs);
    }
}
