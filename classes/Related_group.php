<?php
/**
 * Table Definition for related_group
 */

class Related_group extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'related_group';                   // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $related_group_id;                // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Related_group',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
