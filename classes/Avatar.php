<?php
/**
 * Table Definition for avatar
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Avatar extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'avatar';                          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $original;                        // tinyint(1)  
    public $width;                           // int(4)  primary_key not_null
    public $height;                          // int(4)  primary_key not_null
    public $mediatype;                       // varchar(32)   not_null
    public $filename;                        // varchar(255)  
    public $url;                             // varchar(255)  unique_key
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Avatar',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    # We clean up the file, too

    function delete()
    {
        $filename = $this->filename;
        if (parent::delete()) {
            @unlink(common_avatar_path($filename));
        }
    }

    # Create and save scaled version of this avatar
    # XXX: maybe break into different methods

    function scale($size)
    {

        $image_s = imagecreatetruecolor($size, $size);
        $image_a = $this->to_image();
        $square = min($this->width, $this->height);
        imagecolortransparent($image_s, imagecolorallocate($image_s, 0, 0, 0));
        imagealphablending($image_s, false);
        imagesavealpha($image_s, true);
        imagecopyresampled($image_s, $image_a, 0, 0, 0, 0,
                           $size, $size, $square, $square);

        $ext = ($this->mediattype == 'image/jpeg') ? ".jpeg" : ".png";

        $filename = common_avatar_filename($this->profile_id, $ext, $size, common_timestamp());

        if ($this->mediatype == 'image/jpeg') {
            imagejpeg($image_s, common_avatar_path($filename));
        } else {
            imagepng($image_s, common_avatar_path($filename));
        }

        $scaled = DB_DataObject::factory('avatar');
        $scaled->profile_id = $this->profile_id;
        $scaled->width = $size;
        $scaled->height = $size;
        $scaled->original = false;
        $scaled->mediatype = ($this->mediattype == 'image/jpeg') ? 'image/jpeg' : 'image/png';
        $scaled->filename = $filename;
        $scaled->url = common_avatar_url($filename);
        $scaled->created = DB_DataObject_Cast::dateTime(); # current time

        if ($scaled->insert()) {
            return $scaled;
        } else {
            return null;
        }
    }

    function scale_and_crop($size, $x, $y, $w, $h) 
    {

        $image_s = imagecreatetruecolor($size, $size);
        $image_a = $this->to_image();

        # Retain alpha channel info if possible for .pngs
        $background = imagecolorallocate($image_s, 0, 0, 0);
        ImageColorTransparent($image_s, $background);
        imagealphablending($image_s, false);

        imagecopyresized($image_s, $image_a, 0, 0, $x, $y, $size, $size, $w, $h);

        $ext = ($this->mediattype == 'image/jpeg') ? ".jpeg" : ".png";

        $filename = common_avatar_filename($this->profile_id, $ext, $size, common_timestamp());

        if ($this->mediatype == 'image/jpeg') {
            imagejpeg($image_s, common_avatar_path($filename));
        } else {
            imagepng($image_s, common_avatar_path($filename));
        }

        $cropped = DB_DataObject::factory('avatar');
        $cropped->profile_id = $this->profile_id;
        $cropped->width = $size;
        $cropped->height = $size;
        $cropped->original = false;
        $cropped->mediatype = ($this->mediattype == 'image/jpeg') ? 'image/jpeg' : 'image/png';
        $cropped->filename = $filename;
        $cropped->url = common_avatar_url($filename);
        $cropped->created = DB_DataObject_Cast::dateTime(); # current time

        if ($cropped->insert()) {
            return $cropped;
        } else {
            return NULL;
        }
    }

    function to_image() 
    {
        $filepath = common_avatar_path($this->filename);
        if ($this->mediatype == 'image/gif') {
            return imagecreatefromgif($filepath);
        } else if ($this->mediatype == 'image/jpeg') {
            return imagecreatefromjpeg($filepath);
        } else if ($this->mediatype == 'image/png') {
            return imagecreatefrompng($filepath);
        } else {
            return NULL;
        }
    }
    
    function &pkeyGet($kv) 
    {
        return Memcached_DataObject::pkeyGet('Avatar', $kv);
    }

}
