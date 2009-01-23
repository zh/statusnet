<?php
/**
 * Table Definition for user_group
 */

class User_group extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_group';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $fullname;                        // varchar(255)
    public $homepage;                        // varchar(255)
    public $description;                     // varchar(140)
    public $location;                        // varchar(255)
    public $original_logo;                   // varchar(255)
    public $homepage_logo;                   // varchar(255)
    public $stream_logo;                     // varchar(255)
    public $mini_logo;                       // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('User_group',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function defaultLogo($size)
    {
        static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
                                  AVATAR_STREAM_SIZE => 'stream',
                                  AVATAR_MINI_SIZE => 'mini');
        return theme_path('default-avatar-'.$sizenames[$size].'.png');
    }

    function homeUrl()
    {
        return common_local_url('showgroup',
                                array('nickname' => $this->nickname));
    }

    function permalink()
    {
        return common_local_url('groupbyid',
                                array('id' => $this->id));
    }

    function getNotices($offset, $limit)
    {
        $qry =
          'SELECT notice.* ' .
          'FROM notice JOIN group_inbox ON notice.id = group_inbox.notice_id ' .
          'WHERE group_inbox.group_id = %d ';
        return Notice::getStream(sprintf($qry, $this->id),
                                 'group:notices:'.$this->id,
                                 $offset, $limit);
    }

    function allowedNickname($nickname)
    {
        static $blacklist = array('new');
        return !in_array($nickname, $blacklist);
    }

    function getMembers($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN group_member '.
          'ON profile.id = group_member.profile_id ' .
          'WHERE group_member.group_id = %d ' .
          'ORDER BY group_member.created DESC ';

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $members = new Profile();

        $cnt = $members->query(sprintf($qry, $this->id));

        return $members;
    }

    function setOriginal($filename, $type)
    {
        $orig = clone($this);
        $this->original_logo = common_avatar_url($filename);
        $this->homepage_logo = common_avatar_url($this->scale($filename,
                                                                      AVATAR_PROFILE_SIZE,
                                                                      $type));
        $this->stream_logo = common_avatar_url($this->scale($filename,
                                                                    AVATAR_STREAM_SIZE,
                                                                      $type));
        $this->mini_logo = common_avatar_url($this->scale($filename,
                                                                  AVATAR_MINI_SIZE,
                                                                  $type));
        common_debug(common_log_objstring($this));
        return $this->update($orig);
    }

    function scale($filename, $size, $type)
    {
        $filepath = common_avatar_path($filename);

        if (!file_exists($filepath)) {
            $this->serverError(_('Lost our file.'));
            return;
        }

        $info = @getimagesize($filepath);

        switch ($type) {
        case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif($filepath);
            break;
        case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng($filepath);
            break;
         default:
            $this->serverError(_('Unknown file type'));
            return;
        }

        $image_dest = imagecreatetruecolor($size, $size);

        $background = imagecolorallocate($image_dest, 0, 0, 0);
        ImageColorTransparent($image_dest, $background);
        imagealphablending($image_dest, false);

        imagecopyresized($image_dest, $image_src, 0, 0, $x, $y, $size, $size, $info[0], $info[1]);

        $cur = common_current_user();

        $outname = common_avatar_filename($cur->id,
                                          image_type_to_extension($type),
                                          null,
                                          common_timestamp());

        $outpath = common_avatar_path($outname);

        switch ($type) {
        case IMAGETYPE_GIF:
            imagegif($image_dest, $outpath);
            break;
        case IMAGETYPE_JPEG:
            imagejpeg($image_dest, $outpath);
            break;
        case IMAGETYPE_PNG:
            imagepng($image_dest, $outpath);
            break;
         default:
            $this->serverError(_('Unknown file type'));
            return;
        }

        return $outname;
    }
}
