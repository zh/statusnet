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
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'user_group:notice_ids:' . $this->id,
                              $offset, $limit);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($offset, $limit, $since_id, $max_id, $since)
    {
        $inbox = new Group_inbox();

        $inbox->group_id = $this->id;

        $inbox->selectAdd();
        $inbox->selectAdd('notice_id');

        if ($since_id != 0) {
            $inbox->whereAdd('notice_id > ' . $since_id);
        }

        if ($max_id != 0) {
            $inbox->whereAdd('notice_id <= ' . $max_id);
        }

        if (!is_null($since)) {
            $inbox->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $inbox->orderBy('notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
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

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $members = new Profile();

        $members->query(sprintf($qry, $this->id));
        return $members;
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        $orig = clone($this);
        $this->original_logo = Avatar::url($filename);
        $this->homepage_logo = Avatar::url($imagefile->resize(AVATAR_PROFILE_SIZE));
        $this->stream_logo = Avatar::url($imagefile->resize(AVATAR_STREAM_SIZE));
        $this->mini_logo = Avatar::url($imagefile->resize(AVATAR_MINI_SIZE));
        common_debug(common_log_objstring($this));
        return $this->update($orig);
    }
}
