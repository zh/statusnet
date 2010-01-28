<?php
/**
 * Table Definition for token
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Token extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'token';                           // table name
    public $consumer_key;                    // varchar(255)  primary_key not_null
    public $tok;                             // char(32)  primary_key not_null
    public $secret;                          // char(32)   not_null
    public $type;                            // tinyint(1)   not_null
    public $state;                           // tinyint(1)
    public $verifier;                        // varchar(255)
    public $verified_callback;               // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Token',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
