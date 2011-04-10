<?php
/**
 * Table Definition for profile_tag_inbox
 */

class Profile_tag_inbox extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag_inbox';                     // table name
    public $profile_tag_id;                        // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */

    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile_tag_inbox',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_tag_inbox', $kv);
    }
}
