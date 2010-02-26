<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for building / manipulating an Atom entry in memory
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
 * @category  Feed
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class Atom10EntryException extends Exception
{
}

/**
 * Class for manipulating an Atom entry in memory. Get the entry as an XML
 * string with Atom10Entry::getString().
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Atom10Entry extends XMLStringer
{
    private $namespaces;
    private $categories;
    private $content;
    private $contributors;
    private $id;
    private $links;
    private $published;
    private $rights;
    private $source;
    private $summary;
    private $title;

    function __construct($indent = true) {
        parent::__construct($indent);
        $this->namespaces = array();
    }

    function addNamespace($namespace, $uri)
    {
        $ns = array($namespace => $uri);
        $this->namespaces = array_merge($this->namespaces, $ns);
    }

    function initEntry()
    {

    }

    function endEntry()
    {

    }

    /**
     * Check that all required elements have been set, etc.
     * Throws an Atom10EntryException if something's missing.
     *
     * @return void
     */
    function validate()
    {

    }

    function getString()
    {
        $this->validate();

        $this->initEntry();
        $this->renderEntries();
        $this->endEntry();

        return $this->xw->outputMemory();
    }

}