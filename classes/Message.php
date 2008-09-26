<?php
/**
 * Table Definition for message
 */
require_once INSTALLDIR.'classes/Memcached_DataObject.php';

class Message extends Memcached_DataObject 
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
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Message',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
	function getFrom() {
		return Profile::staticGet('id', $this->from_profile);
	}
	
	function getTo() {
		return Profile::staticGet('id', $this->to_profile);
	}
	
	static function saveNew($from, $to, $content, $source) {
		
		$msg = new Message();
		
		$msg->from_profile = $from;
		$msg->to_profile = $to;
		$msg->content = $content;
		$msg->rendered = common_render_text($content);
		$msg->created = common_sql_now();
		$msg->source = $source;
		
		$result = $msg->insert();
		
		if (!$result) {
			common_log_db_error($msg, 'INSERT', __FILE__);
			return _('Could not insert message.');
		}
		
		$orig = clone($msg);
		$msg->uri = common_local_url('showmessage', array('message' => $msg->id));
		
		$result = $msg->update($orig);
		
		if (!$result) {
			common_log_db_error($msg, 'UPDATE', __FILE__);
			return _('Could not update message with new URI.');
		}
		
		return $msg;
	}
}
