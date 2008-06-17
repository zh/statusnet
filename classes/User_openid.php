<?php
/**
 * Table Definition for user_openid
 */
require_once 'DB/DataObject.php';

class User_openid extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid';                     // table name
    public $url;                             // varchar(255)  primary_key not_null
    public $user_id;                         // int(4)  unique_key not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('User_openid',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
