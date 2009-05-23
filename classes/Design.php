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

if (!defined('LACONICA')) { exit(1); }

/**
 * Table Definition for design
 */

require_once 'classes/Memcached_DataObject';

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
        $out->element('stylesheet', array('type' => 'text/css'),
                      'body { background-color: #' . dechex($this->backgroundcolor) . '} '."\n".
                      '#content { background-color #' . dechex($this->contentcolor) . '} '."\n".
                      '#aside_primary { background-color #'. dechex($this->sidebarcolor) .'} '."\n".
                      'html body { color: #'. dechex($this->textcolor) .'} '."\n".
                      'a { color: #' . dechex($this->linkcolor) . '} '."\n");
    }
}
