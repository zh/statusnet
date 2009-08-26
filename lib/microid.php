<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Microid class
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
 * @category  ID
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * A class for microids
 *
 * @category ID
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      http://microid.org/
 */

class Microid
{
    /** Agent part of the ID. */

    var $agent = null;

    /** Resource part of the ID. */

    var $resource = null;

    /**
     * Constructor
     *
     * @param string $agent    Agent of the ID
     * @param string $resource Resource part
     */

    function __construct($agent, $resource)
    {
        $this->agent    = $agent;
        $this->resource = $resource;

    }

    /**
     * Generate a MicroID string
     *
     * @return string MicroID for agent and resource
     */

    function toString()
    {
        $agent_proto    = $this->_getProto($this->agent);
        $resource_proto = $this->_getProto($this->resource);

        return $agent_proto.'+'.$resource_proto.':sha1:'.
          sha1(sha1($this->agent).sha1($this->resource));
    }

    /**
     * Utility for getting the protocol part of a URI
     *
     * @param string $uri URI to parse
     *
     * @return string scheme part of the URI
     */

    function _getProto($uri)
    {
        $colon = strpos($uri, ':');
        return substr($uri, 0, $colon);
    }
}
