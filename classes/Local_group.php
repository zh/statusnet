<?php
/**
 * Table Definition for local_group
 */
require_once 'classes/Memcached_DataObject.php';

class Local_group extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'local_group';                     // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Local_group',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
