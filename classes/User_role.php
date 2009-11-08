<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Table Definition for user_role
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class User_role extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_role';                       // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $role;                            // varchar(32)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('User_role',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('User_role', $kv);
    }

    const MODERATOR     = 'moderator';
    const ADMINISTRATOR = 'administrator';
}
