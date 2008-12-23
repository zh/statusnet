<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/classes/Notice.php');

class NoticeWrapper extends Notice {

    public $id;                              // int(4)  primary_key not_null
    public $profile_id;                      // int(4)   not_null
    public $uri;                             // varchar(255)  unique_key
    public $content;                         // varchar(140)  
    public $rendered;                        // text()  
    public $url;                             // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $reply_to;                        // int(4)  
    public $is_local;                        // tinyint(1)  
    public $source;                          // varchar(32)  

    var $notices = null;
    var $i = -1;
    
    function __construct($arr) {
        $this->notices = $arr;
    }
    
    function fetch() {
        static $fields = array('id', 'profile_id', 'uri', 'content', 'rendered',
                               'url', 'created', 'modified', 'reply_to', 'is_local', 'source');
        $this->i++;
        if ($this->i >= count($this->notices)) {
            return false;
        } else {
            $n = $this->notices[$this->i];
            foreach ($fields as $f) {
                $this->$f = $n->$f;
            }
            return true;
        }
    }
}