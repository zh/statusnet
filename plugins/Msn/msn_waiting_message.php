<?php
/**
 * Table Definition for msn_plugin_message
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Msn_waiting_message extends Memcached_DataObject {

    public $__table = 'msn_waiting_message'; // table name
    public $id;                              // int primary_key not_null auto_increment
    public $screenname;                      // varchar(255) not_null
    public $message;                         // text not_null
    public $created;                         // datetime() not_null
    public $claimed;                         // datetime()

    /* Static get */
    public function staticGet($k, $v = null) {
        return Memcached_DataObject::staticGet('Msn_waiting_message', $k, $v);
    }

    /**
     * @param mixed $screenname screenname or array of screennames to pull from
     *                          If not specified, checks all queues in the system.
     */
    public static function top($screenname = null) {
        $wm = new Msn_waiting_message();
        if ($screenname) {
            if (is_array($screenname)) {
                // @fixme use safer escaping
                $list = implode("','", array_map('addslashes', $transports));
                $wm->whereAdd("screename in ('$list')");
            } else {
                $wm->screenname = $screenname;
            }
        }
        $wm->orderBy('created');
        $wm->whereAdd('claimed is null');

        $wm->limit(1);

        $cnt = $wm->find(true);

        if ($cnt) {
            # XXX: potential race condition
            # can we force it to only update if claimed is still null
            # (or old)?
            common_log(LOG_INFO, 'claiming msn waiting message id = ' . $wm->id);
            $orig = clone($wm);
            $wm->claimed = common_sql_now();
            $result = $wm->update($orig);
            if ($result) {
                common_log(LOG_INFO, 'claim succeeded.');
                return $wm;
            } else {
                common_log(LOG_INFO, 'claim failed.');
            }
        }
        $wm = null;
        return null;
    }

    /**
     * Release a claimed item.
     */
    public function releaseClaim() {
        // DB_DataObject doesn't let us save nulls right now
        $sql = sprintf("UPDATE msn_waiting_message SET claimed=NULL WHERE id=%d", $this->id);
        $this->query($sql);

        $this->claimed = null;
        $this->encache();
    }
}
