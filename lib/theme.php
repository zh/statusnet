<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Utilities for theme files and paths
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
 * @category  Paths
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Gets the full path of a file in a theme dir based on its relative name
 *
 * @param string $relative relative path within the theme directory
 * @param string $theme    name of the theme; defaults to current theme
 *
 * @return string File path to the theme file
 */

function theme_file($relative, $theme=null)
{
    if (empty($theme)) {
        $theme = common_config('site', 'theme');
    }
    $dir = common_config('theme', 'dir');
    if (empty($dir)) {
        $dir = INSTALLDIR.'/theme';
    }
    return $dir.'/'.$theme.'/'.$relative;
}

/**
 * Gets the full URL of a file in a theme dir based on its relative name
 *
 * @param string $relative relative path within the theme directory
 * @param string $theme    name of the theme; defaults to current theme
 *
 * @return string URL of the file
 */

function theme_path($relative, $theme=null)
{
    if (empty($theme)) {
        $theme = common_config('site', 'theme');
    }

    $path = common_config('theme', 'path');

    if (empty($path)) {
        $path = common_config('site', 'path') . '/theme/';
    }

    if ($path[strlen($path)-1] != '/') {
        $path .= '/';
    }

    if ($path[0] != '/') {
        $path = '/'.$path;
    }

    $server = common_config('theme', 'server');

    if (empty($server)) {
        $server = common_config('site', 'server');
    }

    // XXX: protocol

    return 'http://'.$server.$path.$theme.'/'.$relative;
}
