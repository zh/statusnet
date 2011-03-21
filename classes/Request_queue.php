<?php
/**
 * Table Definition for request_queue
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Request_queue extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'request_queue';       // table name
    public $subscriber;
    public $subscribed;
    public $group_id;
    public $created;

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Confirm_address',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Holder for subscription & group join requests awaiting moderation.',
            'fields' => array(
                'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile making the request'),
                'subscribed' => array('type' => 'int', 'description' => 'remote or local profile to subscribe to, if any'),
                'group_id' => array('type' => 'int', 'description' => 'remote or local group to join, if any'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'unique key' => array(
                'request_queue_subscriber_subscribed_group_id' => array('subscriber', 'subscribed', 'group_id'),
            ),
            'indexes' => array(
                'request_queue_subscriber_created_idx' => array('subscriber', 'created'),
                'request_queue_subscribed_created_idx' => array('subscriber', 'created'),
                'request_queue_group_id_created_idx' => array('group_id', 'created'),
            ),
            'foreign keys' => array(
                'request_queue_subscriber_fkey' => array('profile', array('subscriber' => 'id')),
                'request_queue_subscribed_fkey' => array('profile', array('subscribed' => 'id')),
                'request_queue_group_id_fkey' => array('user_group', array('group_id' => 'id')),
            )
        );
    }
}
