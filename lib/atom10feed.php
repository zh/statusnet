<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for building an Atom feed in memory
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

if (!defined('STATUSNET'))
{
    exit(1);
}

class Atom10FeedException extends Exception
{
}

/**
 * Class for building an Atom feed in memory.  Get the finished doc
 * as a string with Atom10Feed::getString().
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class Atom10Feed extends XMLStringer
{
    public  $xw;
    private $namespaces;
    private $authors;
    private $categories;
    private $contributors;
    private $generator;
    private $icon;
    private $links;
    private $logo;
    private $rights;
    private $subtitle;
    private $title;
    private $published;
    private $updated;
    private $entries;

    /**
     * Constructor
     *
     * @param boolean $indent  flag to turn indenting on or off
     *
     * @return void
     */
    function __construct($indent = true) {
        parent::__construct($indent);
        $this->namespaces = array();
        $this->links      = array();
        $this->entries    = array();
        $this->addNamespace('xmlns', 'http://www.w3.org/2005/Atom');
    }

    /**
     * Add another namespace to the feed
     *
     * @param string $namespace the namespace
     * @param string $uri       namspace uri
     *
     * @return void
     */
    function addNamespace($namespace, $uri)
    {
        $ns = array($namespace => $uri);
        $this->namespaces = array_merge($this->namespaces, $ns);
    }

    function getNamespaces()
    {
        return $this->namespaces;
    }

    function initFeed()
    {
        $this->xw->startDocument('1.0', 'UTF-8');
        $commonAttrs = array('xml:lang' => 'en-US');
        $commonAttrs = array_merge($commonAttrs, $this->namespaces);
        $this->elementStart('feed', $commonAttrs);

        $this->element('id', null, $this->id);
        $this->element('title', null, $this->title);
        $this->element('subtitle', null, $this->subtitle);

        if (!empty($this->logo)) {
            $this->element('logo', null, $this->logo);
        }

        $this->element('updated', null, $this->updated);

        $this->renderLinks();
    }

    /**
     * Check that all required elements have been set, etc.
     * Throws an Atom10FeedException if something's missing.
     *
     * @return void
     */
    function validate()
    {
    }

    function renderLinks()
    {
        foreach ($this->links as $attrs)
        {
            $this->element('link', $attrs, null);
        }
    }

    function addEntryRaw($entry)
    {
        array_push($this->entries, $entry);
    }

    function addEntry($entry)
    {
        array_push($this->entries, $entry->getString());
    }

    function renderEntries()
    {
        foreach ($this->entries as $entry) {
            $this->raw($entry);
        }
    }

    function endFeed()
    {
        $this->elementEnd('feed');
        $this->xw->endDocument();
    }

    function getString()
    {
        $this->validate();

        $this->initFeed();
        $this->renderEntries();
        $this->endFeed();

        return $this->xw->outputMemory();
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setTitle($title)
    {
        $this->title = $title;
    }

    function setSubtitle($subtitle)
    {
        $this->subtitle = $subtitle;
    }

    function setLogo($logo)
    {
        $this->logo = $logo;
    }

    function setUpdated($dt)
    {
        $this->updated = common_date_iso8601($dt);
    }

    function setPublished($dt)
    {
        $this->published = common_date_iso8601($dt);
    }

    /**
     * Adds a link element into the Atom document
     *
     * Assumes you want rel="alternate" and type="text/html" unless
     * you send in $otherAttrs.
     *
     * @param string $uri            the uri the href needs to point to
     * @param array  $otherAttrs     other attributes to stick in
     *
     * @return void
     */
    function addLink($uri, $otherAttrs = null) {
        $attrs = array('href' => $uri);

        if (is_null($otherAttrs)) {
            $attrs['rel']  = 'alternate';
            $attrs['type'] = 'text/html';
        } else {
            $attrs = array_merge($attrs, $otherAttrs);
        }

        array_push($this->links, $attrs);
    }

}
