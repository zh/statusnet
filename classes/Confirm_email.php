<?php
/**
 * Table Definition for confirm_email
 */
require_once 'DB/DataObject.php';

class Confirm_email extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'confirm_email';                   // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $email;                           // varchar(255)   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Confirm_email',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
