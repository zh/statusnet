<?php
/**
 * Table Definition for queue_item
 */
require_once 'DB/DataObject.php';

class Queue_item extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'queue_item';                      // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $transport;                       // varchar(8)   not_null
    public $created;                         // datetime()   not_null
    public $claimed;                         // datetime()  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Queue_item',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey() { return array(false, false); }
}
