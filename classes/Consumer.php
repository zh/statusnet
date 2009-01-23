<?php
/**
 * Table Definition for consumer
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Consumer extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'consumer';                        // table name
    public $consumer_key;                    // varchar(255)  primary_key not_null
    public $seed;                            // char(32)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Consumer',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
