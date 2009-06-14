<?php
/**
 * Table Definition for group_block
 */
require_once 'classes/Memcached_DataObject';

class Group_block extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_block';                     // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $blocked;                         // int(4)  primary_key not_null
    public $blocker;                         // int(4)   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Group_block',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
