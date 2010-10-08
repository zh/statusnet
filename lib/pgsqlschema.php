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
 * @author   Brenda Wallace <shiny@cpan.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PgsqlSchema extends Schema
{

    /**
     * Returns a TableDef object for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $name Name of the table to get
     *
     * @return TableDef tabledef for that table.
     */

    public function getTableDef($name)
    {
        $res = $this->conn->query("SELECT *, column_default as default, is_nullable as Null,
        udt_name as Type, column_name AS Field from INFORMATION_SCHEMA.COLUMNS where table_name = '$name'");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        $td = new TableDef();

        $td->name    = $name;
        $td->columns = array();

        if ($res->numRows() == 0 ) {
          throw new Exception('no such table'); //pretend to be the msyql error. yeah, this sucks.
        }
        $row = array();

        while ($res->fetchInto($row, DB_FETCHMODE_ASSOC)) {
            $cd = new ColumnDef();

            $cd->name = $row['field'];

            $packed = $row['type'];

            if (preg_match('/^(\w+)\((\d+)\)$/', $packed, $match)) {
                $cd->type = $match[1];
                $cd->size = $match[2];
            } else {
                $cd->type = $packed;
            }

            $cd->nullable = ($row['null'] == 'YES') ? true : false;
            $cd->key      = $row['Key'];
            $cd->default  = $row['default'];
            $cd->extra    = $row['Extra'];

            $td->columns[] = $cd;
        }
        return $td;
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
	$onupdate = array();

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
            $sql .= ",\n PRIMARY KEY (" . implode(',', $primary) . ")";
        }

        $sql .= "); ";


        foreach ($uniques as $u) {
            $sql .= "\n CREATE index {$name}_{$u}_idx ON {$name} ($u); ";
        }

        foreach ($indices as $i) {
            $sql .= "CREATE index {$name}_{$i}_idx ON {$name} ($i)";
        }
        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage(). ' SQL was '. $sql);
        }

        return true;
    }

    /**
     * Translate the (mostly) mysql-ish column types into somethings more standard
     * @param string column type
     *
     * @return string postgres happy column type
     */
    private function _columnTypeTranslation($type) {
      $map = array(
      'datetime' => 'timestamp',
      );
      if(!empty($map[$type])) {
        return $map[$type];
      }
      return $type;
    }

    /**
     * Modifies a column in the schema.
     *
     * The name must match an existing column and table.
     *
     * @param string    $table     name of the table
     * @param ColumnDef $columndef new definition of the column.
     *
     * @return boolean success flag
     */

    public function modifyColumn($table, $columndef)
    {
        $sql = "ALTER TABLE $table ALTER COLUMN TYPE " .
          $this->_columnSql($columndef);

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param string $tableName
     * @param array $tableDef
     * @param string $columnName
     * @param array $cd column to create
     *
     * @return string correct SQL for that column
     */

    function columnSql($name, array $cd)
    {
        $line = array();
        $line[] = parent::_columnSql($cd);

        if ($table['foreign keys'][$name]) {
            foreach ($table['foreign keys'][$name] as $foreignTable => $foreignColumn) {
                $line[] = 'references';
                $line[] = $this->quoteId($foreignTable);
                $line[] = '(' . $this->quoteId($foreignColumn) . ')';
            }
        }

        return implode(' ', $line);
    }

    /**
     * Append phrase(s) to an array of partial ALTER TABLE chunks in order
     * to alter the given column from its old state to a new one.
     *
     * @param array $phrase
     * @param string $columnName
     * @param array $old previous column definition as found in DB
     * @param array $cd current column definition
     */
    function appendAlterModifyColumn(array &$phrase, $columnName, array $old, array $cd)
    {
        $prefix = 'ALTER COLUMN ' . $this->quoteIdentifier($columnName) . ' ';

        $oldType = $this->mapType($old);
        $newType = $this->mapType($cd);
        if ($oldType != $newType) {
            $phrase[] .= $prefix . 'TYPE ' . $newType;
        }

        if (!empty($old['not null']) && empty($cd['not null'])) {
            $phrase[] .= $prefix . 'DROP NOT NULL';
        } else if (empty($old['not null']) && !empty($cd['not null'])) {
            $phrase[] .= $prefix . 'SET NOT NULL';
        }

        if (isset($old['default']) && !isset($cd['default'])) {
            $phrase[] . $prefix . 'DROP DEFAULT';
        } else if (!isset($old['default']) && isset($cd['default'])) {
            $phrase[] . $prefix . 'SET DEFAULT ' . $this->quoteDefaultValue($cd);
        }
    }

    /**
     * Quote a db/table/column identifier if necessary.
     *
     * @param string $name
     * @return string
     */
    function quoteIdentifier($name)
    {
        return '"' . $name . '"';
    }

    function mapType($column)
    {
        $map = array('serial' => 'bigserial', // FIXME: creates the wrong name for the sequence for some internal sequence-lookup function, so better fix this to do the real 'create sequence' dance.
                     'numeric' => 'decimal',
                     'datetime' => 'timestamp',
                     'blob' => 'bytea');

        $type = $column['type'];
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if (!empty($column['size'])) {
            $size = $column['size'];
            if ($type == 'integer' &&
                       in_array($size, array('small', 'big'))) {
                $type = $size . 'int';
            }
        }

        return $type;
    }

    // @fixme need name... :P
    function typeAndSize($column)
    {
        if ($column['type'] == 'enum') {
            $vals = array_map(array($this, 'quote'), $column['enum']);
            return "text check ($name in " . implode(',', $vals) . ')';
        } else {
            return parent::typeAndSize($column);
        }
    }

}
