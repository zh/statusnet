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

class MysqlSchema extends Schema
{
    static $_single = null;
    protected $conn = null;


    /**
     * Main public entry point. Use this to get
     * the singleton object.
     *
     * @return Schema the (single) Schema object
     */

    static function get()
    {
        if (empty(self::$_single)) {
            self::$_single = new Schema();
        }
        return self::$_single;
    }

    /**
     * Returns a TableDef object for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $table Name of the table to get
     *
     * @return TableDef tabledef for that table.
     * @throws SchemaTableMissingException
     */

    public function getTableDef($table)
    {
        $def = array();
        $hasKeys = false;

        // Pull column data from INFORMATION_SCHEMA
        $columns = $this->fetchMetaInfo($table, 'COLUMNS', 'ORDINAL_POSITION');
        if (count($columns) == 0) {
            throw new SchemaTableMissingException("No such table: $table");
        }

        foreach ($columns as $row) {

            $name = $row['COLUMN_NAME'];
            $field = array();

            // warning -- 'unsigned' attr on numbers isn't given in DATA_TYPE and friends.
            // It is stuck in on COLUMN_TYPE though (eg 'bigint(20) unsigned')
            list($type, $size) = $this->reverseMapType($row['DATA_TYPE']);
            $field['type'] = $type;
            if ($size !== null) {
                $field['size'] = $size;
            }

            if ($type == 'char' || $type == 'varchar') {
                if ($row['CHARACTER_MAXIMUM_LENGTH'] !== null) {
                    $field['length'] = intval($row['CHARACTER_MAXIMUM_LENGTH']);
                }
            }
            if ($type == 'numeric') {
                // Other int types may report these values, but they're irrelevant.
                // Just ignore them!
                if ($row['NUMERIC_PRECISION'] !== null) {
                    $field['precision'] = intval($row['NUMERIC_PRECISION']);
                }
                if ($row['NUMERIC_SCALE'] !== null) {
                    $field['scale'] = intval($row['NUMERIC_SCALE']);
                }
            }
            if ($row['IS_NULLABLE'] == 'NO') {
                $field['not null'] = true;
            }
            if ($row['COLUMN_DEFAULT'] !== null) {
                $field['default'] = $row['COLUMN_DEFAULT'];
                if ($this->isNumericType($type)) {
                    $field['default'] = intval($field['default']);
                }
            }
            if ($row['COLUMN_KEY'] !== null) {
                // We'll need to look up key info...
                $hasKeys = true;
            }
            if ($row['COLUMN_COMMENT'] !== null && $row['COLUMN_COMMENT'] != '') {
                $field['description'] = $row['COLUMN_COMMENT'];
            }

            $extra = $row['EXTRA'];
            if ($extra) {
                if (preg_match('/(^|\s)auto_increment(\s|$)/i', $extra)) {
                    $field['type'] = 'serial';
                }
                // $row['EXTRA'] may contain 'on update CURRENT_TIMESTAMP'
                // ^ ...... how to specify?
            }

            if ($row['CHARACTER_SET_NAME'] !== null) {
                // @fixme check against defaults?
                //$def['charset'] = $row['CHARACTER_SET_NAME'];
                //$def['collate']  = $row['COLLATION_NAME'];
            }

            $def['fields'][$name] = $field;
        }

        if ($hasKeys) {
            // INFORMATION_SCHEMA's CONSTRAINTS and KEY_COLUMN_USAGE tables give
            // good info on primary and unique keys but don't list ANY info on
            // multi-value keys, which is lame-o. Sigh.
            //
            // Let's go old school and use SHOW INDEX :D
            //
            $keyInfo = $this->fetchIndexInfo($table);
            $keys = array();
            foreach ($keyInfo as $row) {
                $name = $row['Key_name'];
                $column = $row['Column_name'];

                if (!isset($keys[$name])) {
                    $keys[$name] = array();
                }
                $keys[$name][] = $column;

                if ($name == 'PRIMARY') {
                    $type = 'primary key';
                } else if ($row['Non_unique'] == 0) {
                    $type = 'unique keys';
                } else if ($row['Index_type'] == 'FULLTEXT') {
                    $type = 'fulltext indexes';
                } else {
                    $type = 'indexes';
                }
                $keyTypes[$name] = $type;
            }

            foreach ($keyTypes as $name => $type) {
                if ($type == 'primary key') {
                    // there can be only one
                    $def[$type] = $keys[$name];
                } else {
                    $def[$type][$name] = $keys[$name];
                }
            }
        }
        return $def;
    }

    /**
     * Pull the given table properties from INFORMATION_SCHEMA.
     * Most of the good stuff is MySQL extensions.
     *
     * @return array
     * @throws Exception if table info can't be looked up
     */

    function getTableProperties($table, $props)
    {
        $data = $this->fetchMetaInfo($table, 'TABLES');
        if ($data) {
            return $data[0];
        } else {
            throw new SchemaTableMissingException("No such table: $table");
        }
    }

    /**
     * Pull some INFORMATION.SCHEMA data for the given table.
     *
     * @param string $table
     * @return array of arrays
     */
    function fetchMetaInfo($table, $infoTable, $orderBy=null)
    {
        $query = "SELECT * FROM INFORMATION_SCHEMA.%s " .
                 "WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s'";
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, $infoTable, $schema, $table);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return $this->fetchQueryData($sql);
    }

    /**
     * Pull 'SHOW INDEX' data for the given table.
     *
     * @param string $table
     * @return array of arrays
     */
    function fetchIndexInfo($table)
    {
        $query = "SHOW INDEX FROM `%s`";
        $sql = sprintf($query, $table);
        return $this->fetchQueryData($sql);
    }

    /**
     * Pull info from the query into a fun-fun array of dooooom
     *
     * @param string $sql
     * @return array of arrays
     */
    protected function fetchQueryData($sql)
    {
        $res = $this->conn->query($sql);
        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        $out = array();
        $row = array();
        while ($res->fetchInto($row, DB_FETCHMODE_ASSOC)) {
            $out[] = $row;
        }
        $res->free();

        return $out;
    }

    /**
     * Creates a table with the given names and columns.
     *
     * @param string $name    Name of the table
     * @param array  $columns Array of ColumnDef objects
     *                        for new table.
     *
     * @return boolean success flag
     */

    public function createTable($name, $columns)
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
        }

        $idx = $this->_indexList($columns);

        if ($idx['primary']) {
            $sql .= ",\nconstraint primary key (" . implode(',', $idx['primary']) . ")";
        }

        foreach ($idx['uniques'] as $u) {
            $key = $this->_uniqueKey($name, $u);
            $sql .= ",\nunique index $key ($u)";
        }

        foreach ($idx['indices'] as $i) {
            $key = $this->_key($name, $i);
            $sql .= ",\nindex $key ($i)";
        }

        $sql .= ") ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin; ";

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Get the unique index key name for a given column on this table
     */
    function _uniqueKey($tableName, $columnName)
    {
        return $this->_key($tableName, $columnName);
    }

    /**
     * Get the index key name for a given column on this table
     */
    function _key($tableName, $columnName)
    {
        return "{$tableName}_{$columnName}_idx";
    }

    /**
     * Ensures that a table exists with the given
     * name and the given column definitions.
     *
     * If the table does not yet exist, it will
     * create the table. If it does exist, it will
     * alter the table to match the column definitions.
     *
     * @param string $tableName name of the table
     * @param array  $columns   array of ColumnDef
     *                          objects for the table
     *
     * @return boolean success flag
     */

    public function ensureTable($tableName, $columns)
    {
        // XXX: DB engine portability -> toilet

        try {
            $td = $this->getTableDef($tableName);
        } catch (SchemaTableMissingException $e) {
            return $this->createTable($tableName, $columns);
        }

        $cur = $this->_names($td->columns);
        $new = $this->_names($columns);

        $dropIndex  = array();
        $toadd      = array_diff($new, $cur);
        $todrop     = array_diff($cur, $new);
        $same       = array_intersect($new, $cur);
        $tomod      = array();
        $addIndex   = array();
        $tableProps = array();

        foreach ($same as $m) {
            $curCol = $this->_byName($td->columns, $m);
            $newCol = $this->_byName($columns, $m);

            if (!$newCol->equals($curCol)) {
                $tomod[] = $newCol->name;
                continue;
            }

            // Earlier versions may have accidentally left tables at default
            // charsets which might be latin1 or other freakish things.
            if ($this->_isString($curCol)) {
                if ($curCol->charset != 'utf8') {
                    $tomod[] = $newCol->name;
                    continue;
                }
            }
        }

        // Find any indices we have to change...
        $curIdx = $this->_indexList($td->columns);
        $newIdx = $this->_indexList($columns);

        if ($curIdx['primary'] != $newIdx['primary']) {
            if ($curIdx['primary']) {
                $dropIndex[] = 'drop primary key';
            }
            if ($newIdx['primary']) {
                $keys = implode(',', $newIdx['primary']);
                $addIndex[] = "add constraint primary key ($keys)";
            }
        }

        $dropUnique = array_diff($curIdx['uniques'], $newIdx['uniques']);
        $addUnique = array_diff($newIdx['uniques'], $curIdx['uniques']);
        foreach ($dropUnique as $columnName) {
            $dropIndex[] = 'drop key ' . $this->_uniqueKey($tableName, $columnName);
        }
        foreach ($addUnique as $columnName) {
            $addIndex[] = 'add constraint unique key ' . $this->_uniqueKey($tableName, $columnName) . " ($columnName)";;
        }

        $dropMultiple = array_diff($curIdx['indices'], $newIdx['indices']);
        $addMultiple = array_diff($newIdx['indices'], $curIdx['indices']);
        foreach ($dropMultiple as $columnName) {
            $dropIndex[] = 'drop key ' . $this->_key($tableName, $columnName);
        }
        foreach ($addMultiple as $columnName) {
            $addIndex[] = 'add key ' . $this->_key($tableName, $columnName) . " ($columnName)";
        }

        // Check for table properties: make sure we're using a sane
        // engine type and charset/collation.
        // @fixme make the default engine configurable?
        $oldProps = $this->getTableProperties($tableName, array('ENGINE', 'TABLE_COLLATION'));
        if (strtolower($oldProps['ENGINE']) != 'innodb') {
            $tableProps['ENGINE'] = 'InnoDB';
        }
        if (strtolower($oldProps['TABLE_COLLATION']) != 'utf8_bin') {
            $tableProps['DEFAULT CHARSET'] = 'utf8';
            $tableProps['COLLATE'] = 'utf8_bin';
        }

        if (count($dropIndex) + count($toadd) + count($todrop) + count($tomod) + count($addIndex) + count($tableProps) == 0) {
            // nothing to do
            return true;
        }

        // For efficiency, we want this all in one
        // query, instead of using our methods.

        $phrase = array();

        foreach ($dropIndex as $indexSql) {
            $phrase[] = $indexSql;
        }

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

        foreach ($addIndex as $indexSql) {
            $phrase[] = $indexSql;
        }

        foreach ($tableProps as $key => $val) {
            $phrase[] = "$key=$val";
        }

        $sql = 'ALTER TABLE ' . $tableName . ' ' . implode(', ', $phrase);

        common_log(LOG_DEBUG, __METHOD__ . ': ' . $sql);
        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Is this column a string type?
     */
    private function _isString(array $cd)
    {
        $strings = array('char', 'varchar', 'text');
        return in_array(strtolower($cd['type']), $strings);
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param ColumnDef $cd column to create
     *
     * @return string correct SQL for that column
     */

    function columnSql(array $cd)
    {
        $line = array();
        $line[] = parent::_columnSql($cd);

        if ($cd['type'] == 'serial') {
            $line[] = 'auto_increment';
        }

        if (!empty($cd['extra'])) {
            $line[] = $cd['extra']; // hisss boooo
        }

        if (!empty($cd['description'])) {
            $line[] = 'comment';
            $line[] = $this->quote($cd['description']);
        }

        return implode(' ', $line);
    }

    function mapType($column)
    {
        $map = array('serial' => 'int',
                     'integer' => 'int',
                     'numeric' => 'decimal');
        
        $type = $column['type'];
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if (!empty($column['size'])) {
            $size = $column['size'];
            if ($type == 'int' &&
                       in_array($size, array('tiny', 'small', 'medium', 'big'))) {
                $type = $size . $type;
            } else if (in_array($type, array('blob', 'text')) &&
                       in_array($size, array('tiny', 'medium', 'long'))) {
                $type = $size . $type;
            }
        }

        return $type;
    }

    /**
     * Map a MySQL native type back to an independent type + size
     *
     * @param string $type
     * @return array ($type, $size) -- $size may be null
     */
    protected function reverseMapType($type)
    {
        $type = strtolower($type);
        $map = array(
            'decimal' => array('numeric', null),
            'tinyint' => array('int', 'tiny'),
            'smallint' => array('int', 'small'),
            'mediumint' => array('int', 'medium'),
            'bigint' => array('int', 'big'),
            'tinyblob' => array('blob', 'tiny'),
            'mediumblob' => array('blob', 'medium'),
            'longblob' => array('blob', 'long'),
            'tinytext' => array('text', 'tiny'),
            'mediumtext' => array('text', 'medium'),
            'longtext' => array('text', 'long'),
        );
        if (isset($map[$type])) {
            return $map[$type];
        } else {
            return array($type, null);
        }
    }

    function typeAndSize($column)
    {
        if ($column['type'] == 'enum') {
            $vals = array_map(array($this, 'quote'), $column['enum']);
            return 'enum(' . implode(',', $vals) . ')';
        } else if ($this->_isString($column)) {
            return parent::typeAndSize($column) . ' CHARSET utf8';
        } else {
            return parent::typeAndSize($column);
        }
    }
}
