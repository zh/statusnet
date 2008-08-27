<?php
/**
 * Table Definition for foreign_user
 */
require_once 'DB/DataObject.php';

class Foreign_user extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_user';                    // table name
    public $id;                              // int(4)  primary_key not_null
    public $service;                         // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key not_null
    public $nickname;                        // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Foreign_user',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
	function getForeignUser($user_id, $service) {
		
		$fuser = DB_DataObject::factory('foreign_user');
		$fuser->whereAdd("service = $service");
		$fuser->whereAdd("user_id = $user_id");
		$fuser->limit(1);
		
		if ($fuser->find()) {
			$fuser->fetch();
			return $fuser;
		}
		
		return NULL;		
	}
	
	
	static function save($fields) {
		
		extract($fields);
				
		$fuser = new Foreign_user();
		
		$fuser->id = $id;
		$fuser->service = $service;
		$fuser->uri = $uri;
		$fuser->nickname = $nickname;		
		$fuser->user_id = $user_id;
		$fuser->credentials = $credentials;
		$fuser->created = common_sql_now();
		
		$result = $fuser->insert();

		if (!$result) {
			common_log_db_error($fuser, 'INSERT', __FILE__);
			return FALSE;
		}

		return $fuser;
	}
	
}
