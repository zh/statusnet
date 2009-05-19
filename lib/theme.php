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
    if (!$theme) {
        $theme = common_config('site', 'theme');
    }
    return INSTALLDIR.'/theme/'.$theme.'/'.$relative;
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
    if (!$theme) {
        $theme = common_config('site', 'theme');
    }
    $server = common_config('theme', 'server');
    if ($server) {
        return 'http://'.$server.'/'.$theme.'/'.$relative;
    } else {
        return common_path('theme/'.$theme.'/'.$relative);
    }
}

/**
 * Gets the full URL of a file in a skin dir based on its relative name
 *
 * @param string $relative relative path within the theme, skin directory
 * @param string $theme    name of the theme; defaults to current theme
 * @param string $skin    name of the skin; defaults to current theme
 *
 * @return string URL of the file
 */

function skin_path($relative, $theme=null, $skin=null)
{
    if (!$theme) {
        $theme = common_config('site', 'theme');
    }
    if (!$skin) {
        $skin = common_config('site', 'skin');
    }
    $server = common_config('theme', 'server');
    if ($server) {
        return 'http://'.$server.'/'.$theme.'/skin/'.$skin.'/'.$relative;
    } else {
        return common_path('theme/'.$theme.'/skin/'.$skin.'/'.$relative);
    }
}

