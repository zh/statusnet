<?php
/**
 * Table Definition for oauth_application
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Oauth_application extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_application';               // table name
    public $id;                              // int(4)  primary_key not_null
    public $owner;                           // int(4)   not_null
    public $consumer_key;                    // varchar(255)   not_null
    public $name;                            // varchar(255)   not_null
    public $description;                     // varchar(255)
    public $icon;                            // varchar(255)   not_null
    public $source_url;                      // varchar(255)
    public $organization;                    // varchar(255)
    public $homepage;                        // varchar(255)
    public $callback_url;                    // varchar(255)   not_null
    public $type;                            // tinyint(1)
    public $access_type;                     // tinyint(1)
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) {
	return Memcached_DataObject::staticGet('Oauth_application',$k,$v);
    }
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
