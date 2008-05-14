<?php
if (!defined('MICROBLOG')) { exit(1) }
/**
 * Table Definition for user
 */
require_once 'DB/DataObject.php';

class User extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user';                            // table name
    public $id;                              // int(4)  primary_key not_null
    public $password;                        // varchar(255)  
    public $email;                           // varchar(255)  unique_key
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('User',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
	function getProfile() {
		return Profile::staticGet($this->$id);
	}
	
	function isSubscribed($other) {
		assert(!is_null($other));
		$sub = DB_DataObject::factory('subscription');
		$sub->subscriber = $this->id;
		$sub->subscribed = $other->id;
		return $sub->find();
	}
}
