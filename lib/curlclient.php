n<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility class for wrapping Curl
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
 * @category  HTTP
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define(CURLCLIENT_VERSION, "0.1");

/**
 * Wrapper for Curl
 *
 * Makes Curl HTTP client calls within our HTTPClient framework
 *
 * @category HTTP
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class CurlClient extends HTTPClient
{
    function __construct()
    {
    }

    function head($url, $headers=null)
    {
        $ch = curl_init($url);

        $this->setup($ch);

        curl_setopt_array($ch,
                          array(CURLOPT_NOBODY => true));

        if (!is_null($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);

        curl_close($ch);

        return $this->parseResults($result);
    }

    function get($url, $headers=null)
    {
        $ch = curl_init($url);

        $this->setup($ch);

        if (!is_null($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);

        curl_close($ch);

        return $this->parseResults($result);
    }

    function post($url, $headers=null, $body=null)
    {
        $ch = curl_init($url);

        $this->setup($ch);

        curl_setopt($ch, CURLOPT_POST, true);

        if (!is_null($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!is_null($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);

        curl_close($ch);

        return $this->parseResults($result);
    }

    function setup($ch)
    {
        curl_setopt_array($ch,
                          array(CURLOPT_USERAGENT => $this->userAgent(),
                                CURLOPT_HEADER => true,
                                CURLOPT_RETURNTRANSFER => true));
    }

    function userAgent()
    {
        $version = curl_version();
        return parent::userAgent() . " CurlClient/".CURLCLIENT_VERSION . " cURL/" . $version['version'];
    }

    function parseResults($results)
    {
        $resp = new HTTPResponse();

        $lines = explode("\r\n", $results);

        if (preg_match("#^HTTP/1.[01] (\d\d\d) .+$#", $lines[0], $match)) {
            $resp->code = $match[1];
        } else {
            throw Exception("Bad format: initial line is not HTTP status line");
        }

        $lastk = null;

        for ($i = 1; $i < count($lines); $i++) {
            $l =& $lines[$i];
            if (mb_strlen($l) == 0) {
                $resp->body = implode("\r\n", array_slice($lines, $i + 1));
                break;
            }
            if (preg_match("#^(\S+):\s+(.*)$#", $l, $match)) {
                $k = $match[1];
                $v = $match[2];

                if (array_key_exists($k, $resp->headers)) {
                    if (is_array($resp->headers[$k])) {
                        $resp->headers[$k][] = $v;
                    } else {
                        $resp->headers[$k] = array($resp->headers[$k], $v);
                    }
                } else {
                    $resp->headers[$k] = $v;
                }
                $lastk = $k;
            } else if (preg_match("#^\s+(.*)$#", $l, $match)) {
                // continuation line
                if (is_null($lastk)) {
                    throw Exception("Bad format: initial whitespace in headers");
                }
                $h =& $resp->headers[$lastk];
                if (is_array($h)) {
                    $n = count($h);
                    $h[$n-1] .= $match[1];
                } else {
                    $h .= $match[1];
                }
            }
        }

        return $resp;
    }
}
