<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Importer class for Delicious.com bookmarks
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

class DeliciousBookmarkImporter extends QueueHandler
{
    /**
     * Return the transport for this queue handler
     *
     * @return string 'dlcsbkmk'
     */

    function transport()
    {
        return 'dlcsbkmk';
    }

    /**
     * Handle the data
     * 
     * @param array $data array of user, dt, dd
     *
     * @return boolean success value
     */

    function handle($data)
    {
        list($user, $dt, $dd) = $data;

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

        $saved = Notice_bookmark::saveNew($user->getProfile(),
                                          $title,
                                          $url,
                                          $tags,
                                          $description,
                                          array('created' => $created));

        return true;
    }
}
