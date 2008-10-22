<?php
/**
 * Table Definition for notice_inbox
 */
require_once 'classes/Memcached_DataObject';

class Notice_inbox extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_inbox';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $source;                          // tinyint(1)   default_1

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Notice_inbox',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
