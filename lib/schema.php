<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Database schema utilities
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Database
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class representing the database schema
 *
 * A class representing the database schema. Can be used to
 * manipulate the schema -- especially for plugins and upgrade
 * utilities.
 *
 * @category Database
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class Schema
{
    static $_single = null;
    protected $conn = null;

    protected function __construct()
    {
        // XXX: there should be an easier way to do this.
        $user = new User();
        $this->conn = $user->getDatabaseConnection();
        $user->free();
        unset($user);
    }

    static function get()
    {
        if (empty(self::$_single)) {
            self::$_single = new Schema();
        }
        return self::$_single;
    }

    public function getTableDef($name)
    {
        $res =& $this->conn->query('DESCRIBE ' . $name);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        $td = new TableDef();

        $td->name    = $name;
        $td->columns = array();

        $row = array();

        while ($res->fetchInto($row, DB_FETCHMODE_ASSOC)) {

            $cd = new ColumnDef();

            $cd->name = $row['Field'];

            $packed = $row['Type'];

            if (preg_match('/^(\w+)\((\d+)\)$/', $packed, $match)) {
                $cd->type = $match[1];
                $cd->size = $match[2];
            } else {
                $cd->type = $packed;
            }

            $cd->nullable = ($row['Null'] == 'YES') ? true : false;
            $cd->key      = $row['Key'];
            $cd->default  = $row['Default'];
            $cd->extra    = $row['Extra'];

            $td->columns[] = $cd;
        }

        return $td;
    }

    public function getColumnDef($table, $column)
    {
        $td = $this->getTableDef($table);

        foreach ($td->columns as $cd) {
            if ($cd->name == $column) {
                return $cd;
            }
        }

        return null;
    }

    public function getIndexDef($table, $index)
    {
        return null;
    }

    public function createTable($name, $columns, $indices=null)
    {
        $uniques = array();
        $primary = array();
        $indices = array();

        $sql = "CREATE TABLE $name (\n";

        for ($i = 0; $i < count($columns); $i++) {

            $cd =& $columns[$i];

            if ($i > 0) {
                $sql .= ",\n";
            }

            $sql .= $this->_columnSql($cd);

            switch ($cd->key) {
             case 'UNI':
                $uniques[] = $cd->name;
                break;
             case 'PRI':
                $primary[] = $cd->name;
                break;
             case 'MUL':
                $indices[] = $cd->name;
                break;
            }
        }

        if (count($primary) > 0) { // it really should be...
            $sql .= ",\nconstraint primary key (" . implode(',', $primary) . ")";
        }

        foreach ($uniques as $u) {
            $sql .= ",\nunique index {$name}_{$u}_idx ($u)";
        }

        foreach ($indices as $i) {
            $sql .= ",\nindex {$name}_{$i}_idx ($i)";
        }

        $sql .= "); ";

        common_debug($sql);

        $res =& $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function dropTable($name)
    {
        $res =& $this->conn->query("DROP TABLE $name");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function createIndex($table, $columnNames, $name = null)
    {
        if (!is_array($columnNames)) {
            $columnNames = array($columnNames);
        }

        if (empty($name)) {
            $name = "$table_".implode("_", $columnNames)."_idx";
        }

        $res =& $this->conn->query("ALTER TABLE $table ADD INDEX $name (".implode(",", $columnNames).")");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function dropIndex($table, $name)
    {
        $res =& $this->conn->query("ALTER TABLE $table DROP INDEX $name");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function addColumn($table, $columndef)
    {
        $sql = "ALTER TABLE $table ADD COLUMN " . $this->_columnSql($columndef);

        $res =& $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function modifyColumn($table, $columndef)
    {
        $sql = "ALTER TABLE $table MODIFY COLUMN " . $this->_columnSql($columndef);

        $res =& $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function dropColumn($table, $columnName)
    {
        $sql = "ALTER TABLE $table DROP COLUMN $columnName";

        $res =& $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    public function ensureTable($tableName, $columns, $indices=null)
    {
        // XXX: DB engine portability -> toilet

        try {
            $td = $this->getTableDef($tableName);
        } catch (Exception $e) {
            if (preg_match('/no such table/', $e->getMessage())) {
                return $this->createTable($tableName, $columns, $indices);
            } else {
                throw $e;
            }
        }

        $cur = $this->_names($td->columns);
        $new = $this->_names($columns);

        $toadd  = array_diff($new, $cur);
        $todrop = array_diff($cur, $new);

        $same  = array_intersect($new, $cur);

        foreach ($same as $m) {
            $curCol = $this->_byName($td->columns, $m);
            $newCol = $this->_byName($columns, $m);

            if (!$newCol->equals($curCol)) {
                $tomod[] = $newCol->name;
            }
        }

        if (count($toadd) + count($todrop) + count($tomod) == 0) {
            // nothing to do
            return true;
        }

        // For efficiency, we want this all in one
        // query, instead of using our methods.

        $phrase = array();

        foreach ($toadd as $columnName) {
            $cd = $this->_byName($columns, $columnName);
            $phrase[] = 'ADD COLUMN ' . $this->_columnSql($cd);
        }

        foreach ($todrop as $columnName) {
            $phrase[] = 'DROP COLUMN ' . $columnName;
        }

        foreach ($tomod as $columnName) {
            $cd = $this->_byName($columns, $columnName);
            $phrase[] = 'MODIFY COLUMN ' . $this->_columnSql($cd);
        }

        $sql = 'ALTER TABLE ' . $tableName . ' ' . implode(', ', $phrase);

        $res =& $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    function _names($cds)
    {
        $names = array();

        foreach ($cds as $cd) {
            $names[] = $cd->name;
        }

        return $names;
    }

    function _byName($cds, $name)
    {
        foreach ($cds as $cd) {
            if ($cd->name == $name) {
                return $cd;
            }
        }

        return null;
    }

    function _columnSql($cd)
    {
        $sql = "{$cd->name} ";

        if (!empty($cd->size)) {
            $sql .= "{$cd->type}({$cd->size}) ";
        } else {
            $sql .= "{$cd->type} ";
        }

        if (!empty($cd->default)) {
            $sql .= "default {$cd->default} ";
        } else {
            $sql .= ($cd->nullable) ? "null " : "not null ";
        }

        return $sql;
    }
}

class TableDef
{
    public $name;
    public $columns;
}

class ColumnDef
{
    public $name;
    public $type;
    public $size;
    public $nullable;
    public $key;
    public $default;
    public $extra;

    function __construct($name, $type, $size=null, $nullable=true,
                         $key=null, $default=null, $extra=null) {
        $this->name     = strtolower($name);
        $this->type     = strtolower($type);
        $this->size     = $size+0;
        $this->nullable = $nullable;
        $this->key      = $key;
        $this->default  = $default;
        $this->extra    = $extra;
    }

    function equals($other)
    {
        return ($this->name == $other->name &&
                $this->_typeMatch($other) &&
                $this->_defaultMatch($other) &&
                $this->_nullMatch($other) &&
                $this->key == $other->key);
    }

    function _typeMatch($other)
    {
        switch ($this->type) {
         case 'integer':
         case 'int':
            return ($other->type == 'integer' ||
                    $other->type == 'int');
            break;
         default:
            return ($this->type == $other->type &&
                    $this->size == $other->size);
        }
    }

    function _defaultMatch($other)
    {
        return ((is_null($this->default) && is_null($other->default)) ||
                ($this->default == $other->default));
    }

    function _nullMatch($other)
    {
        return ((!is_null($this->default) && !is_null($other->default) &&
                 $this->default == $other->default) ||
                ($this->nullable == $other->nullable));
    }
}

class IndexDef
{
    public $name;
    public $table;
    public $columns;
}
