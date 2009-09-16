<?php
/**
 * Laconica, the distributed open-source microblogging tool
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
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Utility class for doing HTTP-related stuff
 *
 * We make HTTP calls in several places, and we have several different
 * ways of doing them. This class hides the specifics of what underlying
 * library (curl or PHP-HTTP or whatever) that's used.
 *
 * @category HTTP
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class HTTPResponse
{
    var $code = null;
    var $headers = null;
    var $body = null;
}

class HTTPClientUtil
{
    function __construct()
    {
    }

    function head($url, $headers)
    {
    }

    function get($url, $headers)
    {
    }

    function post($url, $headers, $body)
    {
    }

    function put($url, $headers, $body)
    {
    }

    function delete($url, $headers)
    {
    }
}
