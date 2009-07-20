<?php
/**
 * Laconica, the distributed open-source microblogging tool
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
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
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
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class Schema
{
    protected $db = null;

    static function get()
    {

    }

    public function getTableDef($name)
    {
    }

    public function getColumnDef($table, $column)
    {
    }

    public function getIndexDef($table, $index)
    {
    }

    public function createTable($name, $columns, $indices=null)
    {
    }

    public function dropTable($name)
    {
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
}

class IndexDef
{
    public $name;
    public $table;
    public $columns;
}
