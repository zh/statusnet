<?php
/**
 * Table Definition for avatar
 */
require_once 'DB/DataObject.php';

class Avatar extends DB_DataObject
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
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Avatar',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	function validateMediatype() {
		return Validate::string($this->mediatype, array('min_length' => 1, 'max_length' => 32));
	}

	function validateFilename() {
		return Validate::string($this->filename, array('min_length' => 1, 'max_length' => 255));
	}

	function validateUrl() {
		return Validate::uri($this->url, array('allowed_schemes' => array('http', 'https')));
	}
}
