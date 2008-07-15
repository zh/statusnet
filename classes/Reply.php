<?php
/**
 * Table Definition for reply
 */
require_once 'DB/DataObject.php';

class Reply extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'reply';                           // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $replied_id;                      // int(4)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Reply',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
