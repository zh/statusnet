<?php
/**
 * Table Definition for login_token
 *
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Login_token extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'login_token';         // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $token;                           // char(32)  not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Login_token',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /*
    DB_DataObject calculates the sequence key(s) by taking the first key returned by the keys() function.
    In this case, the keys() function returns user_id as the first key. user_id is not a sequence, but
    DB_DataObject's sequenceKey() will incorrectly think it is. Then, since the sequenceKey() is a numeric
    type, but is not set to autoincrement in the database, DB_DataObject will create a _seq table and
    manage the sequence itself. This is not the correct behavior for the user_id in this class.
    So we override that incorrect behavior, and simply say there is no sequence key.
    */
    function sequenceKey()
    {
        return array(false,false);
    }
}
