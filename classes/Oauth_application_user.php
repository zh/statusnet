<?php
/**
 * Table Definition for oauth_application_user
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Oauth_application_user extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_application_user';          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $application_id;                  // int(4)  primary_key not_null
    public $access_type;                     // tinyint(1)
    public $created;                         // datetime   not_null

    /* Static get */
    function staticGet($k,$v=NULL) {
	return Memcached_DataObject::staticGet('Oauth_application_user',$k,$v);
    }
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
