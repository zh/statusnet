<?php
/**
 * Table Definition for user_openid_trustroot
 */
require_once INSTALLDIR.'/classes/Plugin_DataObject.php';

class User_openid_trustroot extends Plugin_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid_trustroot';                     // table name
    public $trustroot;                         // varchar(255) primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('User_openid_trustroot',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
    
    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('User_openid_trustroot', $kv);
    }

    /**
    * Get the TableDef object that represents the table backing this class
    * @return TableDef TableDef instance
    */
    function tableDef()
    {
        return new TableDef($this->__table,
                             array(new ColumnDef('trustroot', 'varchar',
                                                 '255', false, 'PRI'),
                                   new ColumnDef('user_id', 'integer',
                                                 null, false, 'PRI'),
                                   new ColumnDef('created', 'datetime',
                                                 null, false),
                                   new ColumnDef('modified', 'timestamp')));
    }
}
