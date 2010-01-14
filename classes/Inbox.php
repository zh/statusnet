<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Data class for user location preferences
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
 * @category  Data
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Inbox extends Memcached_DataObject
{
    const BOXCAR = 128;

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'inbox';                           // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_ids;                      // blob

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Inbox',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Create a new inbox from existing Notice_inbox stuff
     */

    static function initialize($user_id)
    {
        $inbox = Inbox::fromNoticeInbox($user_id);

        unset($inbox->fake);

        $result = $inbox->insert();

        if (!$result) {
            common_log_db_error($inbox, 'INSERT', __FILE__);
            return null;
        }

        return $inbox;
    }

    static function fromNoticeInbox($user_id)
    {
        $ids = array();

        $ni = new Notice_inbox();

        $ni->user_id = $user_id;
        $ni->selectAdd();
        $ni->selectAdd('notice_id');
        $ni->orderBy('notice_id DESC');
        $ni->limit(0, 1024);

        if ($ni->find()) {
            while($ni->fetch()) {
                $ids[] = $ni->notice_id;
            }
        }

        $ni->free();
        unset($ni);

        $inbox = new Inbox();

        $inbox->user_id = $user_id;
        $inbox->notice_ids = call_user_func_array('pack', array_merge(array('N*'), $ids));
        $inbox->fake = true;

        return $inbox;
    }

    static function insertNotice($user_id, $notice_id)
    {
        $inbox = Inbox::staticGet('user_id', $user_id);

        if (empty($inbox) || $inbox->fake) {
            $inbox = Inbox::initialize($user_id);
        }

        if (empty($inbox)) {
            return false;
        }

        $result = $inbox->query(sprintf('UPDATE inbox '.
                                        'set notice_ids = concat(cast(0x%08x as binary(4)), '.
                                        'substr(notice_ids, 1, 4092)) '.
                                        'WHERE user_id = %d',
                                        $notice_id, $user_id));

        if ($result) {
            $c = self::memcache();

            if (!empty($c)) {
                $c->delete(self::cacheKey('inbox', 'user_id', $user_id));
            }
        }

        return $result;
    }

    static function bulkInsert($notice_id, $user_ids)
    {
        foreach ($user_ids as $user_id)
        {
            Inbox::insertNotice($user_id, $notice_id);
        }
    }

    function stream($user_id, $offset, $limit, $since_id, $max_id, $since, $own=false)
    {
        $inbox = Inbox::staticGet('user_id', $user_id);

        if (empty($inbox)) {
            $inbox = Inbox::fromNoticeInbox($user_id);
            if (empty($inbox)) {
                return array();
            } else {
                $inbox->encache();
            }
        }

        $ids = unpack('N*', $inbox->notice_ids);

        // XXX: handle since_id
        // XXX: handle max_id

        $ids = array_slice($ids, $offset, $limit);

        return $ids;
    }
}
