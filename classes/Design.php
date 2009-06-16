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

        $out->element('style', array('type' => 'text/css'),
                      'html, body { background-color: #' . $bgcolor->hexValue() . '} '."\n".
                      '#content { background-color: #' . $ccolor->hexValue() . '} '."\n".
                      '#aside_primary { background-color: #'. $sbcolor->hexValue() .'} '."\n".
                      'html body { color: #'. $tcolor->hexValue() .'} '."\n".
                      'a { color: #' . $lcolor->hexValue() . '} '."\n");
    }
}
