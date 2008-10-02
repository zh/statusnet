<?php
/**
 * Table Definition for fave
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Fave extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave';                            // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Fave',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	static function addNew($user, $notice) {
		$fave = new Fave();
		$fave->user_id = $user->id;
		$fave->notice_id = $notice->id;
		if (!$fave->insert()) {
			common_log_db_error($fave, 'INSERT', __FILE__);
			return false;
		}
		return $fave;
	}
	
	function &pkeyGet($kv) {
		return Memcached_DataObject('Fave', $kv);
	}
}
