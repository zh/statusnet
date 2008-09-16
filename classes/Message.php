<?php
/**
 * Table Definition for message
 */
require_once 'DB/DataObject.php';

class Message extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'message';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key
    public $from_profile;                    // int(4)   not_null
    public $to_profile;                      // int(4)   not_null
    public $content;                         // varchar(140)  
    public $rendered;                        // text()  
    public $url;                             // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $source;                          // varchar(32)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Message',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
	function getFrom() {
		return Profile::staticGet('id', $this->from_profile);
	}
	
	function getTo() {
		return Profile::staticGet('id', $this->to_profile);
	}
}
