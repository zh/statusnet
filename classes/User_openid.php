<?php
/**
 * Table Definition for user_openid
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class User_openid extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid';                     // table name
    public $canonical;                       // varchar(255)  primary_key not_null
    public $display;                         // varchar(255)  unique_key not_null
    public $user_id;                         // int(4)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null) { return Memcached_DataObject::staticGet('User_openid',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
