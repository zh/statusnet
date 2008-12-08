<?php
/**
 * Table Definition for profile_block
 */
require_once 'classes/Memcached_DataObject';

class Profile_block extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_block';                   // table name
    public $blocker;                         // int(4)  primary_key not_null
    public $blocked;                         // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile_block',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function get($blocker, $blocked) {
		return Profile_block::pkeyGet(array('blocker' => $blocker,
                                            'blocked' => $blocked));
    }
}
