<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility for creating new tag: URIs
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
 * @category  URI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Mint tag: URIs
 *
 * tag: URIs are unique identifiers according to http://tools.ietf.org/html/rfc4151.
 *
 * We use them for creating URIs for things that can't be HTTP retrieved.
 *
 * @category URI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class TagURI
{
    /**
     * Return the base part of a tag URI
     *
     * Note: use mint() instead.
     *
     * @return string Tag URI base to use
     */

    static function base()
    {
        $base = common_config('integration', 'taguri');

        if (empty($base)) {

            $base = common_config('site', 'server').','.date('Y-m-d');

            $pathPart = trim(common_config('site', 'path'), '/');

            if (!empty($pathPart)) {
                $base .= ':'.str_replace('/', ':', $pathPart);
            }
        }

        return $base;
    }

    /**
     * Make a new tag URI
     *
     * Builds the proper base and creates all the parts
     *
     * @return string minted URI
     */

    static function mint()
    {
        $base = self::base();

        $args = func_get_args();

        $format = array_shift($args);

        $extra = vsprintf($format, $args);

        return 'tag:'.$base.':'.$extra;
    }
}
