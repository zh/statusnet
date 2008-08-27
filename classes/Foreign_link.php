<?php
/**
 * Table Definition for foreign_link
 */
require_once 'DB/DataObject.php';

class Foreign_link extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_link';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $foreign_id;                      // int(4)  primary_key not_null
    public $service;                         // int(4)  primary_key not_null
    public $credentials;                     // varchar(255)  
    public $noticesync;                      // tinyint(1)   not_null default_1
    public $friendsync;                      // tinyint(1)   not_null default_2
    public $profilesync;                     // tinyint(1)   not_null default_1
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Foreign_link',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
