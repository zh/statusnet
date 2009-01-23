<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Abstraction for an image file
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
 * @category  Image
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * A wrapper on uploaded files
 *
 * Makes it slightly easier to accept an image file from upload.
 *
 * @category Image
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ImageFile
{
    var $filename = null;
    var $barename = null;
    var $type = null;
    var $height = null;
    var $width = null;

    function __construct($filename=null, $type=null, $width=null, $height=null)
    {
        $this->filename = $filename;
        $this->type = $type;
        $this->width = $type;
        $this->height = $type;
    }

    static function fromUpload($param='upload')
    {
        switch ($_FILES[$param]['error']) {
        case UPLOAD_ERR_OK: // success, jump out
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception(_('That file is too big.'));
            return;
        case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Partial upload.'));
            return;
        default:
            throw new Exception(_('System error uploading file.'));
            return;
        }

        $imagefile = new ImageFile($_FILES[$param]['tmp_name']);
        $info = @getimagesize($imagefile->filename);

        if (!$info) {
            @unlink($imagefile->filename);
            throw new Exception(_('Not an image or corrupt file.'));
            return;
        }

        $imagefile->width = $info[0];
        $imagefile->height = $info[1];

        switch ($info[2]) {
        case IMAGETYPE_GIF:
        case IMAGETYPE_JPEG:
        case IMAGETYPE_PNG:
            $imagefile->type = $info[2];
            break;
        default:
            @unlink($imagefile->filename);
            throw new Exception(_('Unsupported image file format.'));
            return;
        }

        return $imagefile;
    }

    function unlink()
    {
        @unlink($this->filename);
    }
}