<?php
/**
 * Table Definition for user_flag_profile
 */
require_once 'classes/Memcached_DataObject.php';

class User_flag_profile extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_flag_profile';               // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $flag;                            // varchar(8)  
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('User_flag_profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
