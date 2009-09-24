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

    public function createIndex($name, $table, $columns)
    {
    }

    public function dropIndex($name, $table)
    {
    }

    public function addColumn($table, $columndef)
    {
    }

    public function modifyColumn($table, $column, $columndef)
    {
    }

    public function dropColumn($table, $column)
    {
    }

    public function ensureTable($name, $columns, $indices)
    {
        $def = $this->tableDef($name);
        if (empty($def)) {
            return $this->createTable($name, $columns, $indices);
        }
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

    function __construct($name, $type, $size=null, $nullable=null,
                         $key=null, $default=null, $extra=null) {
        $this->name     = $name;
        $this->type     = $type;
        $this->size     = $size;
        $this->nullable = $nullable;
        $this->key      = $key;
        $this->default  = $default;
        $this->extra    = $extra;
    }
}

class IndexDef
{
    public $name;
    public $table;
    public $columns;
}
