<?php
/*
StatusNet Plugin: 0.9
Plugin Name: Minify
Description: Minifies resources (Javascript and CSS)
Version: 0.1
Author: Craig Andrews <candrews@integralblue.com>
Author URI: http://candrews.integralblue.com/
*/

/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

/**
 * @package MinifyPlugin
 * @maintainer Craig Andrews <candrews@integralblue.com>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class MinifyPlugin extends Plugin
{

    /**
     * Add Minification related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @return boolean hook return
     */

    function onStartInitializeRouter($m)
    {
        $m->connect('main/min',
                    array('action' => 'minify'));
        return true;
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'MinifyAction':
            require_once(INSTALLDIR.'/plugins/Minify/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
            return false;
         default:
            return true;
        }
    }

    function onLoginAction($action, &$login)
    {
        switch ($action)
        {
         case 'minify':
            $login = true;
            return false;
         default:
            return true;
        }
    }

    function onStartScriptElement($action,&$src,&$type) {
        $url = parse_url($src);
        if( empty($url->scheme) && empty($url->host) && empty($url->query) && empty($url->fragment))
        {
            $src = $this->minifyUrl($src);
        }
    }

    function onStartCssLinkElement($action,&$src,&$theme,&$media) {
        $allowThemeMinification =
            is_null(common_config('theme', 'dir'))
            && is_null(common_config('theme', 'path'))
            && is_null(common_config('theme', 'server'));
        $url = parse_url($src);
        if( empty($url->scheme) && empty($url->host) && empty($url->query) && empty($url->fragment))
        {
            if(!isset($theme)) {
                $theme = common_config('site', 'theme');
            }
            if($allowThemeMinification && file_exists(INSTALLDIR.'/local/theme/'.$theme.'/'.$src)) {
                $src = $this->minifyUrl('local/theme/'.$theme.'/'.$src);
            } else if($allowThemeMinification && file_exists(INSTALLDIR.'/theme/'.$theme.'/'.$src)) {
                $src = $this->minifyUrl('theme/'.$theme.'/'.$src);
            }else if(file_exists(INSTALLDIR.'/'.$src)){
                $src = $this->minifyUrl($src);
            }
        }
    }

    function minifyUrl($src) {
        return common_local_url('minify',null,array('f' => $src ,v => STATUSNET_VERSION));
    }
}

