<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * Wrapper for Memcached_DataObject which knows its own schema definition.
 * Builds its own damn settings from a schema definition.
 *
 * @author Brion Vibber <brion@status.net>
 */
abstract class Managed_DataObject extends Memcached_DataObject
{
    /**
     * The One True Thingy that must be defined and declared.
     */
    public static abstract function schemaDef();

    /**
     * get/set an associative array of table columns
     *
     * @access public
     * @return array (associative)
     */
    function table()
    {
        $table = self::schemaDef();
        return array_map(array($this, 'columnBitmap'), $table['fields']);
    }

    /**
     * get/set an  array of table primary keys
     *
     * Key info is pulled from the table definition array.
     * 
     * @access private
     * @return array
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * Get a sequence key
     *
     * Returns the first serial column defined in the table, if any.
     *
     * @access private
     * @return array (column,use_native,sequence_name)
     */

    function sequenceKey()
    {
        $table = self::schemaDef();
        foreach ($table['fields'] as $name => $column) {
            if ($column['type'] == 'serial') {
                // We have a serial/autoincrement column.
                // Declare it to be a native sequence!
                return array($name, true, false);
            }
        }

        // No sequence key on this table.
        return array(false, false, false);
    }

    /**
     * Return key definitions for DB_DataObject and Memcache_DataObject.
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        $keys = array();
        $table = self::schemaDef();

        if (!empty($table['unique keys'])) {
            foreach ($table['unique keys'] as $idx => $fields) {
                foreach ($fields as $name) {
                    $keys[$name] = 'U';
                }
            }
        }

        if (!empty($table['primary key'])) {
            foreach ($table['primary key'] as $name) {
                $keys[$name] = 'K';
            }
        }
        return $keys;
    }

    /**
     * Build the appropriate DB_DataObject bitfield map for this field.
     *
     * @param array $column
     * @return int
     */
    function columnBitmap($column)
    {
        $type = 0;

        switch ($column['type']) {
        case 'int':
        case 'serial':
        case 'numeric':
            // Doesn't need quoting.
            $type |= DB_DATAOBJECT_INT;
            break;
        default:
            // Value needs quoting in SQL literal statements.
            $type |= DB_DATAOBJECT_STR;
        }

        switch ($column['type']) {
        case 'blob':
            $type |= DB_DATAOBJECT_BLOB;
            break;
        case 'text':
            $type |= DB_DATAOBJECT_TXT;
            break;
        case 'datetime':
            $type |= DB_DATAOBJECT_DATE;
            $type |= DB_DATAOBJECT_TIME;
            break;
        case 'timestamp':
            $type |= DB_DATAOBJECT_MYSQLTIMESTAMP;
            break;
        }

        if (!empty($column['not null'])) {
            $type |= DB_DATAOBJECT_NOTNULL;
        }

        return $type;
    }
}