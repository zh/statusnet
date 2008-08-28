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
	
	// XXX:  This only returns a 1->1 single obj mapping.  Change?  Or make
	// a getForeignUsers() that returns more than one? --Zach
	static function getForeignUser($id, $service) {		
		$fuser = new Foreign_user();
		$fuser->whereAdd("service = $service");
		$fuser->whereAdd("id = $id");
		$fuser->limit(1);
		
		if ($fuser->find()) {
			$fuser->fetch();
			return $fuser;
		}
		
		return NULL;		
	}
	
}
