<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

abstract class Plugin_DataObject extends Memcached_DataObject
{
    function table() {
        static $table = null;
        if($table == null) {
            $table = array();
            $DB = $this->getDatabaseConnection();
            $dbtype = $DB->phptype;
            $tableDef = $this->tableDef();
            foreach($tableDef->columns as $columnDef){
                switch(strtoupper($columnDef->type)) {
                    /*shamelessly copied from DB_DataObject_Generator*/
                    case 'INT':
                    case 'INT2':    // postgres
                    case 'INT4':    // postgres
                    case 'INT8':    // postgres
                    case 'SERIAL4': // postgres
                    case 'SERIAL8': // postgres
                    case 'INTEGER':
                    case 'TINYINT':
                    case 'SMALLINT':
                    case 'MEDIUMINT':
                    case 'BIGINT':
                        $type = DB_DATAOBJECT_INT;
                        if ($columnDef->size == 1) {
                            $type +=  DB_DATAOBJECT_BOOL;
                        }
                        break;
                   
                    case 'REAL':
                    case 'DOUBLE':
                    case 'DOUBLE PRECISION': // double precision (firebird)
                    case 'FLOAT':
                    case 'FLOAT4': // real (postgres)
                    case 'FLOAT8': // double precision (postgres)
                    case 'DECIMAL':
                    case 'MONEY':  // mssql and maybe others
                    case 'NUMERIC':
                    case 'NUMBER': // oci8 
                        $type = DB_DATAOBJECT_INT; // should really by FLOAT!!! / MONEY...
                        break;
                        
                    case 'YEAR':
                        $type = DB_DATAOBJECT_INT; 
                        break;
                        
                    case 'BIT':
                    case 'BOOL':   
                    case 'BOOLEAN':   
                    
                        $type = DB_DATAOBJECT_BOOL;
                        // postgres needs to quote '0'
                        if ($dbtype == 'pgsql') {
                            $type +=  DB_DATAOBJECT_STR;
                        }
                        break;
                        
                    case 'STRING':
                    case 'CHAR':
                    case 'VARCHAR':
                    case 'VARCHAR2':
                    case 'TINYTEXT':
                    
                    case 'ENUM':
                    case 'SET':         // not really but oh well
                    
                    case 'POINT':       // mysql geometry stuff - not really string - but will do..
                    
                    case 'TIMESTAMPTZ': // postgres
                    case 'BPCHAR':      // postgres
                    case 'INTERVAL':    // postgres (eg. '12 days')
                    
                    case 'CIDR':        // postgres IP net spec
                    case 'INET':        // postgres IP
                    case 'MACADDR':     // postgress network Mac address.
                    
                    case 'INTEGER[]':   // postgres type
                    case 'BOOLEAN[]':   // postgres type
                    
                        $type = DB_DATAOBJECT_STR;
                        break;
                    
                    case 'TEXT':
                    case 'MEDIUMTEXT':
                    case 'LONGTEXT':
                        
                        $type = DB_DATAOBJECT_STR + DB_DATAOBJECT_TXT;
                        break;
                    
                    
                    case 'DATE':    
                        $type = DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE;
                        break;
                        
                    case 'TIME':    
                        $type = DB_DATAOBJECT_STR + DB_DATAOBJECT_TIME;
                        break;    
                        
                    
                    case 'DATETIME': 
                         
                        $type = DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME;
                        break;    
                        
                    case 'TIMESTAMP': // do other databases use this???
                        
                        $type = ($dbtype == 'mysql') ?
                            DB_DATAOBJECT_MYSQLTIMESTAMP : 
                            DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME;
                        break;    
                        
                    
                    case 'BLOB':       /// these should really be ignored!!!???
                    case 'TINYBLOB':
                    case 'MEDIUMBLOB':
                    case 'LONGBLOB':
                    
                    case 'CLOB': // oracle character lob support
                    
                    case 'BYTEA':   // postgres blob support..
                        $type = DB_DATAOBJECT_STR + DB_DATAOBJECT_BLOB;
                        break;
                        
                    default:
                        throw new Exception("Cannot handle datatype: $columnDef->type");
                }
                if(! $columnDef->nullable) {
                    $type+=DB_DATAOBJECT_NOTNULL;
                }
                $table[$columnDef->name]=$type;
            }
        }
        return $table;
    }

    function keys() {
        static $keys = null;
        if($keys == null) {
            $keys = array();
            $tableDef = $this->tableDef();
            foreach($tableDef->columns as $columnDef){
                if($columnDef->key != null){
                    $keys[] = $columnDef->name;
                }
            }
        }
        return $keys;
    }

    function sequenceKey() {
        static $sequenceKey = null;
        if($sequenceKey == null) {
            $sequenceKey = array(false,false);
            $tableDef = $this->tableDef();
            foreach($tableDef->columns as $columnDef){
                if($columnDef->key == 'PRI' && $columnDef->auto_increment){
                    $sequenceKey=array($columnDef->name,true);
                }
            }
        }
        return $sequenceKey;
    }

    /**
    * Get the TableDef object that represents the table backing this class
    * Ideally, this function would a static function, but PHP doesn't allow
    * abstract static functions
    * @return TableDef TableDef instance
    */
    abstract function tableDef();
}

