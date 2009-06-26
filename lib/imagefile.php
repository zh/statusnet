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
    var $id;
    var $filepath;
    var $barename;
    var $type;
    var $height;
    var $width;

    function __construct($id=null, $filepath=null, $type=null, $width=null, $height=null)
    {
        $this->id = $id;
        $this->filepath = $filepath;

        $info = @getimagesize($this->filepath);
        $this->type = ($info) ? $info[2]:$type;
        $this->width = ($info) ? $info[0]:$width;
        $this->height = ($info) ? $info[1]:$height;
    }

    static function fromUpload($param='upload')
    {
        switch ($_FILES[$param]['error']) {
         case UPLOAD_ERR_OK: // success, jump out
            break;
         case UPLOAD_ERR_INI_SIZE:
         case UPLOAD_ERR_FORM_SIZE:
            throw new Exception(sprintf(_('That file is too big. The maximum file size is %d.'),
                ImageFile::maxFileSize()));
            return;
         case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Partial upload.'));
            return;
         default:
            throw new Exception(_('System error uploading file.'));
            return;
        }

        $info = @getimagesize($_FILES[$param]['tmp_name']);

        if (!$info) {
            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Not an image or corrupt file.'));
            return;
        }

        if ($info[2] !== IMAGETYPE_GIF &&
            $info[2] !== IMAGETYPE_JPEG &&
            $info[2] !== IMAGETYPE_PNG) {

            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Unsupported image file format.'));
            return;
        }

        return new ImageFile(null, $_FILES[$param]['tmp_name']);
    }

    function resize($size, $x = 0, $y = 0, $w = null, $h = null)
    {
        $w = ($w === null) ? $this->width:$w;
        $h = ($h === null) ? $this->height:$h;

        if (!file_exists($this->filepath)) {
            throw new Exception(_('Lost our file.'));
            return;
        }

        // Don't crop/scale if it isn't necessary
        if ($size === $this->width
            && $size === $this->height
            && $x === 0
            && $y === 0
            && $w === $this->width
            && $h === $this->height) {

            $outname = Avatar::filename($this->id,
                                        image_type_to_extension($this->type),
                                        $size,
                                        common_timestamp());
            $outpath = Avatar::path($outname);
            @copy($this->filepath, $outpath);
            return $outname;
        }

        switch ($this->type) {
         case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif($this->filepath);
            break;
         case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg($this->filepath);
            break;
         case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng($this->filepath);
            break;
         default:
            throw new Exception(_('Unknown file type'));
            return;
        }

        $image_dest = imagecreatetruecolor($size, $size);

        if ($this->type == IMAGETYPE_GIF || $this->type == IMAGETYPE_PNG) {

            $transparent_idx = imagecolortransparent($image_src);

            if ($transparent_idx >= 0) {

                $transparent_color = imagecolorsforindex($image_src, $transparent_idx);
                $transparent_idx = imagecolorallocate($image_dest, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($image_dest, 0, 0, $transparent_idx);
                imagecolortransparent($image_dest, $transparent_idx);

            } elseif ($this->type == IMAGETYPE_PNG) {

                imagealphablending($image_dest, false);
                $transparent = imagecolorallocatealpha($image_dest, 0, 0, 0, 127);
                imagefill($image_dest, 0, 0, $transparent);
                imagesavealpha($image_dest, true);

            }
        }

        imagecopyresampled($image_dest, $image_src, 0, 0, $x, $y, $size, $size, $w, $h);

        $outname = Avatar::filename($this->id,
                                    image_type_to_extension($this->type),
                                    $size,
                                    common_timestamp());

        $outpath = Avatar::path($outname);

        switch ($this->type) {
         case IMAGETYPE_GIF:
            imagegif($image_dest, $outpath);
            break;
         case IMAGETYPE_JPEG:
            imagejpeg($image_dest, $outpath, 100);
            break;
         case IMAGETYPE_PNG:
            imagepng($image_dest, $outpath);
            break;
         default:
            throw new Exception(_('Unknown file type'));
            return;
        }

        imagedestroy($image_src);
        imagedestroy($image_dest);

        return $outname;
    }

    function unlink()
    {
        @unlink($this->filename);
    }

    static function maxFileSize()
    {
        $value = ImageFile::maxFileSizeInt();

        if ($value > 1024 * 1024) {
            return ($value/(1024*1024)).'Mb';
        } else if ($value > 1024) {
            return ($value/(1024)).'kB';
        } else {
            return $value;
        }
    }

    static function maxFileSizeInt()
    {
        return min(ImageFile::strToInt(ini_get('post_max_size')),
                   ImageFile::strToInt(ini_get('upload_max_filesize')),
                   ImageFile::strToInt(ini_get('memory_limit')));
    }

    static function strToInt($str)
    {
        $unit = substr($str, -1);
        $num = substr($str, 0, -1);

        switch(strtoupper($unit)){
         case 'G':
            $num *= 1024;
         case 'M':
            $num *= 1024;
         case 'K':
            $num *= 1024;
        }

        return $num;
    }
}