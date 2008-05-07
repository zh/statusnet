<?php
/**
 * Table Definition for profile
 */
require_once 'DB/DataObject.php';

class Profile extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)   not_null
    public $fullname;                        // varchar(255)  
    public $profileurl;                      // varchar(255)  
    public $homepage;                        // varchar(255)  
    public $bio;                             // varchar(140)  
    public $location;                        // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
