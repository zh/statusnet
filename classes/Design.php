<?php
/*
 * Laconica - the distributed open-source microblogging tool
 * Copyright (C) 2009, Control Yourself, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) {
    exit(1);
}

define('BACKGROUND_ON', 1);
define('BACKGROUND_OFF', 2);
define('BACKGROUND_TILE', 4);

/**
 * Table Definition for design
 */

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';
require_once INSTALLDIR . '/lib/webcolor.php';

class Design extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'design';                          // table name
    public $id;                              // int(4)  primary_key not_null
    public $backgroundcolor;                 // int(4)
    public $contentcolor;                    // int(4)
    public $sidebarcolor;                    // int(4)
    public $textcolor;                       // int(4)
    public $linkcolor;                       // int(4)
    public $backgroundimage;                 // varchar(255)
    public $disposition;                     // tinyint(1)   default_1

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Design',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function showCSS($out)
    {
        try {

            $bgcolor = new WebColor($this->backgroundcolor);
            $ccolor  = new WebColor($this->contentcolor);
            $sbcolor = new WebColor($this->sidebarcolor);
            $tcolor  = new WebColor($this->textcolor);
            $lcolor  = new WebColor($this->linkcolor);

        } catch (WebColorException $e) {
            // This shouldn't happen
            common_log(LOG_ERR, "Unable to create color for design $id.",
                __FILE__);
        }

        $css  = 'body { background-color: #' . $bgcolor->hexValue() . ' }' . "\n";
        $css .= '#content, #site_nav_local_views .current a { background-color: #';
        $css .= $ccolor->hexValue() . '} '."\n";
        $css .= '#aside_primary { background-color: #'. $sbcolor->hexValue() . ' }' . "\n";
        $css .= 'html body { color: #'. $tcolor->hexValue() . ' }'. "\n";
        $css .= 'a { color: #' . $lcolor->hexValue() . ' }' . "\n";

        if (!empty($this->backgroundimage) &&
            $this->disposition & BACKGROUND_ON) {

           $repeat = ($this->disposition & BACKGROUND_TILE) ?
               'background-repeat:repeat;' :
               'background-repeat:no-repeat;';

            $css .= 'body { background-image:url(' .
                Design::url($this->backgroundimage) .
                '); ' . $repeat . ' background-attachment:fixed; }' . "\n";
        }

        $out->element('style', array('type' => 'text/css'), $css);

    }

    static function filename($id, $extension, $extra=null)
    {
        return $id . (($extra) ? ('-' . $extra) : '') . $extension;
    }

    static function path($filename)
    {
        $dir = common_config('background', 'dir');

        if ($dir[strlen($dir)-1] != '/') {
            $dir .= '/';
        }

        return $dir . $filename;
    }

    static function url($filename)
    {
        $path = common_config('background', 'path');

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        $server = common_config('background', 'server');

        if (empty($server)) {
            $server = common_config('site', 'server');
        }

        // XXX: protocol

        return 'http://'.$server.$path.$filename;
    }

    function setDisposition($on, $off, $tile)
    {
        if ($on) {
            $this->disposition |= BACKGROUND_ON;
        } else {
            $this->disposition &= ~BACKGROUND_ON;
        }

        if ($off) {
            $this->disposition |= BACKGROUND_OFF;
        } else {
            $this->disposition &= ~BACKGROUND_OFF;
        }

        if ($tile) {
            $this->disposition |= BACKGROUND_TILE;
        } else {
            $this->disposition &= ~BACKGROUND_TILE;
        }
    }

}
