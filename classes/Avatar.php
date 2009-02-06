<?php
/**
 * Table Definition for avatar
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Avatar extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'avatar';                          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $original;                        // tinyint(1)  
    public $width;                           // int(4)  primary_key not_null
    public $height;                          // int(4)  primary_key not_null
    public $mediatype;                       // varchar(32)   not_null
    public $filename;                        // varchar(255)  
    public $url;                             // varchar(255)  unique_key
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Avatar',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    # We clean up the file, too

    function delete()
    {
        $filename = $this->filename;
        if (parent::delete()) {
            @unlink(common_avatar_path($filename));
        }
    }
    
    function &pkeyGet($kv) 
    {
        return Memcached_DataObject::pkeyGet('Avatar', $kv);
    }

}
