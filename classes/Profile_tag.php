<?php
/**
 * Table Definition for profile_tag
 */
require_once 'classes/Memcached_DataObject';

class Profile_tag extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag';                     // table name
    public $tagger;                          // int(4)  primary_key not_null
    public $tagged;                          // int(4)  primary_key not_null
    public $tag;                             // varchar(64)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile_tag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
