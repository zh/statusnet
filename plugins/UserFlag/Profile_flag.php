<?php
/**
 * Table Definition for profile_flag
 */
require_once 'classes/Memcached_DataObject.php';

class Profile_flag extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_flag';                    // table name
    public $flag;                            // varchar(8)  primary_key not_null
    public $display;                         // varchar(255)  
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile_flag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
