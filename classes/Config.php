<?php
/**
 * Table Definition for config
 */
require_once 'classes/Memcached_DataObject.php';

class Config extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'config';                          // table name
    public $section;                         // varchar(32)  primary_key not_null
    public $setting;                         // varchar(32)  primary_key not_null
    public $value;                           // varchar(255)

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Config',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}
