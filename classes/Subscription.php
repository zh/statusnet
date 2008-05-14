<?php
if (!defined('MICROBLOG')) { exit(1) }
/**
 * Table Definition for subscription
 */
require_once 'DB/DataObject.php';

class Subscription extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'subscription';                    // table name
    public $subscriber;                      // int(4)  primary_key not_null
    public $subscribed;                      // int(4)  primary_key not_null
    public $token;                           // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Subscription',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
