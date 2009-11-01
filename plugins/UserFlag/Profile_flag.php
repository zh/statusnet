<?php
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

class Profile_flag extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_flag';                    // table name
    public $flag;                            // varchar(8)  primary_key not_null
    public $display;                         // varchar(255)
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile_flag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function table() {
        return array(
                     'flag'      => DB_DATAOBJECT_STR,
                     'display'   => DB_DATAOBJECT_STR,
                     'created'   => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME
                     );
    }

    function keys() {
        return array('flag');
    }
}
