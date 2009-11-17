<?php
/**
 * Table Definition for user_openid
 */
require_once INSTALLDIR.'/classes/Plugin_DataObject.php';

class User_openid extends Plugin_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_openid';                     // table name
    public $canonical;                       // varchar(255)  primary_key not_null
    public $display;                         // varchar(255)  unique_key not_null
    public $user_id;                         // int(4)   not_null
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('User_openid',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function hasOpenID($user_id)
    {
        $oid = new User_openid();

        $oid->user_id = $user_id;

        $cnt = $oid->find();

        return ($cnt > 0);
    }

    /**
    * Get the TableDef object that represents the table backing this class
    * @return TableDef TableDef instance
    */
    function tableDef()
    {
        return new TableDef($this->__table,
                             array(new ColumnDef('canonical', 'varchar',
                                                 '255', false, 'PRI'),
                                   new ColumnDef('display', 'varchar',
                                                 '255', false),
                                   new ColumnDef('user_id', 'integer',
                                                 null, false, 'MUL'),
                                   new ColumnDef('created', 'datetime',
                                                 null, false),
                                   new ColumnDef('modified', 'timestamp')));
    }
}
