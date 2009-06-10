<?php
/**
 * Table Definition for fave
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Fave extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave';                            // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Fave',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function addNew($user, $notice) {
        $fave = new Fave();
        $fave->user_id = $user->id;
        $fave->notice_id = $notice->id;
        if (!$fave->insert()) {
            common_log_db_error($fave, 'INSERT', __FILE__);
            return false;
        }
        return $fave;
    }

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Fave', $kv);
    }

    function stream($user_id, $offset=0, $limit=NOTICES_PER_PAGE)
    {
        $ids = Notice::stream(array('Fave', '_streamDirect'),
                              array($user_id),
                              'fave:ids_by_user:'.$user_id,
                              $offset, $limit);
        return $ids;
    }

    function _streamDirect($user_id, $offset, $limit, $since_id, $max_id, $since)
    {
        $fav = new Fave();

        $fav->user_id = $user_id;

        $fav->selectAdd();
        $fav->selectAdd('notice_id');

        if ($since_id != 0) {
            $fav->whereAdd('notice_id > ' . $since_id);
        }

        if ($max_id != 0) {
            $fav->whereAdd('notice_id <= ' . $max_id);
        }

        if (!is_null($since)) {
            $fav->whereAdd('modified > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        // NOTE: we sort by fave time, not by notice time!

        $fav->orderBy('modified DESC');

        if (!is_null($offset)) {
            $fav->limit($offset, $limit);
        }

        $ids = array();

        if ($fav->find()) {
            while ($fav->fetch()) {
                $ids[] = $fav->notice_id;
            }
        }

        return $ids;
    }
}
