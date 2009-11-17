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

    // Bit flags
    public static $readAccess  = 1;
    public static $writeAccess = 2;

    public static $browser = 1;
    public static $desktop = 2;

    function getConsumer()
    {
        return Consumer::staticGet('consumer_key', $this->consumer_key);
    }

    static function maxDesc()
    {
        $desclimit = common_config('application', 'desclimit');
        // null => use global limit (distinct from 0!)
        if (is_null($desclimit)) {
            $desclimit = common_config('site', 'textlimit');
        }
        return $desclimit;
    }

    static function descriptionTooLong($desc)
    {
        $desclimit = self::maxDesc();
        return ($desclimit > 0 && !empty($desc) && (mb_strlen($desc) > $desclimit));
    }

    function setAccessFlags($read, $write)
    {
        if ($read) {
            $this->access_type |= self::$readAccess;
        } else {
            $this->access_type &= ~self::$readAccess;
        }

        if ($write) {
            $this->access_type |= self::$writeAccess;
        } else {
            $this->access_type &= ~self::$writeAccess;
        }
    }

}
