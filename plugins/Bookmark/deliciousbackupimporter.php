<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Importer class for Delicious.com backups
 * 
 * PHP version 5
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
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Importer class for Delicious bookmarks
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class DeliciousBackupImporter
{
    /**
     * Import an in-memory bookmark list to a user's account
     *
     * Take a delicious.com backup file (same as Netscape bookmarks.html)
     * and import to StatusNet as Bookmark activities.
     *
     * The document format is terrible. It consists of a <dl> with
     * a bunch of <dt>'s, occasionally with <dd>'s.
     * There are sometimes <p>'s lost inside.
     *
     * @param User   $user User whose feed we're going to fill
     * @param string $body Body of the file
     *
     * @return void
     */

    function importBookmarks($user, $body)
    {
        $doc = $this->importHTML($body);

        $dls = $doc->getElementsByTagName('dl');

        if ($dls->length != 1) {
            throw new ClientException(_("Bad import file."));
        }

        $dl = $dls->item(0);

        $children = $dl->childNodes;

        $dt = null;

        for ($i = 0; $i < $children->length; $i++) {
            try {
                $child = $children->item($i);
                if ($child->nodeType != XML_ELEMENT_NODE) {
                    continue;
                }
                common_log(LOG_INFO, $child->tagName);
                switch (strtolower($child->tagName)) {
                case 'dt':
                    if (!empty($dt)) {
                        // No DD provided
                        $this->importBookmark($user, $dt);
                        $dt = null;
                    }
                    $dt = $child;
                    break;
                case 'dd':
                    $dd = $child;

                    $saved = $this->importBookmark($user, $dt, $dd);

                    $dt = null;
                    $dd = null;
                case 'p':
                    common_log(LOG_INFO, 'Skipping the <p> in the <dl>.');
                    break;
                default:
                    common_log(LOG_WARNING, 
                               "Unexpected element $child->tagName ".
                               " found in import.");
                }
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                $dt = $dd = null;
            }
        }
    }

    /**
     * Import a single bookmark
     * 
     * Takes a <dt>/<dd> pair. The <dt> has a single
     * <a> in it with some non-standard attributes.
     * 
     * A <dt><dt><dd> sequence will appear as a <dt> with
     * anothe <dt> as a child. We handle this case recursively. 
     *
     * @param User       $user User to import data as
     * @param DOMElement $dt   <dt> element
     * @param DOMElement $dd   <dd> element
     *
     * @return Notice imported notice
     */

    function importBookmark($user, $dt, $dd = null)
    {
        // We have to go squirrelling around in the child nodes
        // on the off chance that we've received another <dt>
        // as a child.

        for ($i = 0; $i < $dt->childNodes->length; $i++) {
            $child = $dt->childNodes->item($i);
            if ($child->nodeType == XML_ELEMENT_NODE) {
                if ($child->tagName == 'dt' && !is_null($dd)) {
                    $this->importBookmark($user, $dt);
                    $this->importBookmark($user, $child, $dd);
                    return;
                }
            }
        }

        $as = $dt->getElementsByTagName('a');

        if ($as->length == 0) {
            throw new ClientException(_("No <A> tag in a <DT>."));
        }

        $a = $as->item(0);
                    
        $private = $a->getAttribute('private');

        if ($private != 0) {
            throw new ClientException(_('Skipping private bookmark.'));
        }

        if (!empty($dd)) {
            $description = $dd->nodeValue;
        } else {
            $description = null;
        }

        $title   = $a->nodeValue;
        $url     = $a->getAttribute('href');
        $tags    = $a->getAttribute('tags');
        $addDate = $a->getAttribute('add_date');
        $created = common_sql_date(intval($addDate));

        $saved = Notice_bookmark::saveNew($user,
                                          $title,
                                          $url,
                                          $tags,
                                          $description,
                                          array('created' => $created));

        return $saved;
    }

    /**
     * Parse some HTML
     *
     * Hides the errors that the dom parser returns
     *
     * @param string $body Data to import
     *
     * @return DOMDocument parsed document
     */

    function importHTML($body)
    {
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.
        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok  = $dom->loadHTML($body);
        error_reporting($old);

        if ($ok) {
            return $dom;
        } else {
            return null;
        }
    }
}
