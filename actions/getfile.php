<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Returns a given file attachment, allowing private sites to only allow
 * access to file attachments after login.
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
 * @category  Personal
 * @package   StatusNet
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once 'MIME/Type.php';

/**
 * Action for getting a file attachment
 *
 * @category Personal
 * @package  StatusNet
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class GetfileAction extends Action
{
    /**
     * Path of file to return
     */

    var $path = null;

    /**
     * Get file name
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $filename = $this->trimmed('filename');
        $path = null;

        if ($filename) {
            $path = common_config('attachments', 'dir') . $filename;
        }

        if (empty($path) or !file_exists($path)) {
            $this->clientError(_('No such file.'), 404);
            return false;
        }
        if (!is_readable($path)) {
            $this->clientError(_('Cannot read file.'), 403);
            return false;
        }

        $this->path = $path;
        return true;
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Last-modified date for file
     *
     * @return int last-modified date as unix timestamp
     */

    function lastModified()
    {
        return filemtime($this->path);
    }

    /**
     * etag for file
     *
     * This returns the same data (inode, size, mtime) as Apache would,
     * but in decimal instead of hex.
     *
     * @return string etag http header
     */
    function etag()
    {
        $stat = stat($this->path);
        return '"' . $stat['ino'] . '-' . $stat['size'] . '-' . $stat['mtime'] . '"';
    }

    /**
     * Handle input, produce output
     *
     * @param array $args $_REQUEST contents
     *
     * @return void
     */

    function handle($args)
    {
        // undo headers set by PHP sessions
        $sec = session_cache_expire() * 60;
        header('Expires: ' . date(DATE_RFC1123, time() + $sec));
        header('Cache-Control: public, max-age=' . $sec);
        header('Pragma: public');

        parent::handle($args);

        $path = $this->path;
        header('Content-Type: ' . MIME_Type::autoDetect($path));
        readfile($path);
    }
}
