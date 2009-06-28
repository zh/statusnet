<?php
/**
 * Table Definition for queue_item
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Queue_item extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'queue_item';                      // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $transport;                       // varchar(8)  primary_key not_null
    public $created;                         // datetime()   not_null
    public $claimed;                         // datetime()

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Queue_item',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey()
    { return array(false, false); }

    static function top($transport) {

        $qi = new Queue_item();
        $qi->transport = $transport;
        $qi->orderBy('created');
        $qi->whereAdd('claimed is null');

        $qi->limit(1);

        $cnt = $qi->find(true);

        if ($cnt) {
            # XXX: potential race condition
            # can we force it to only update if claimed is still null
            # (or old)?
            common_log(LOG_INFO, 'claiming queue item = ' . $qi->notice_id . ' for transport ' . $transport);
            $orig = clone($qi);
            $qi->claimed = common_sql_now();
            $result = $qi->update($orig);
            if ($result) {
                common_log(LOG_INFO, 'claim succeeded.');
                return $qi;
            } else {
                common_log(LOG_INFO, 'claim failed.');
            }
        }
        $qi = null;
        return null;
    }

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Queue_item', $kv);
    }
}
