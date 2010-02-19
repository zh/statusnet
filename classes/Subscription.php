<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Table Definition for subscription
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Subscription extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'subscription';                    // table name
    public $subscriber;                      // int(4)  primary_key not_null
    public $subscribed;                      // int(4)  primary_key not_null
    public $jabber;                          // tinyint(1)   default_1
    public $sms;                             // tinyint(1)   default_1
    public $token;                           // varchar(255)
    public $secret;                          // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Subscription',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Subscription', $kv);
    }

    /**
     * Make a new subscription
     *
     * @param Profile $subscriber party to receive new notices
     * @param Profile $other      party sending notices; publisher
     *
     * @return Subscription new subscription
     */

    static function start($subscriber, $other)
    {
        if (!$subscriber->hasRight(Right::SUBSCRIBE)) {
            throw new Exception(_('You have been banned from subscribing.'));
        }

        if (self::exists($subscriber, $other)) {
            throw new Exception(_('Already subscribed!'));
        }

        if ($other->hasBlocked($subscriber)) {
            throw new Exception(_('User has blocked you.'));
        }

        if (Event::handle('StartSubscribe', array($subscriber, $other))) {

            $sub = new Subscription();

            $sub->subscriber = $subscriber->id;
            $sub->subscribed = $other->id;
            $sub->created    = common_sql_now();

            $result = $sub->insert();

            if (!$result) {
                common_log_db_error($sub, 'INSERT', __FILE__);
                throw new Exception(_('Could not save subscription.'));
            }

            $sub->notify();

            self::blow('user:notices_with_friends:%d', $subscriber->id);

            $subscriber->blowSubscriptionsCount();
            $other->blowSubscribersCount();

            $otherUser = User::staticGet('id', $other->id);

            if (!empty($otherUser) &&
                $otherUser->autosubscribe &&
                !self::exists($other, $subscriber) &&
                !$subscriber->hasBlocked($other)) {

                $auto = new Subscription();

                $auto->subscriber = $subscriber->id;
                $auto->subscribed = $other->id;
                $auto->created    = common_sql_now();

                $result = $auto->insert();

                if (!$result) {
                    common_log_db_error($auto, 'INSERT', __FILE__);
                    throw new Exception(_('Could not save subscription.'));
                }

                $auto->notify();
            }

            Event::handle('EndSubscribe', array($subscriber, $other));
        }

        return true;
    }

    function notify()
    {
        # XXX: add other notifications (Jabber, SMS) here
        # XXX: queue this and handle it offline
        # XXX: Whatever happens, do it in Twitter-like API, too

        $this->notifyEmail();
    }

    function notifyEmail()
    {
        $subscribedUser = User::staticGet('id', $this->subscribed);

        if (!empty($subscribedUser)) {

            $subscriber = Profile::staticGet('id', $this->subscriber);

            mail_subscribe_notify_profile($subscribedUser, $subscriber);
        }
    }

    /**
     * Cancel a subscription
     *
     */

    function cancel($subscriber, $other)
    {
        if (!self::exists($subscriber, $other)) {
            throw new Exception(_('Not subscribed!'));
        }

        // Don't allow deleting self subs

        if ($subscriber->id == $other->id) {
            throw new Exception(_('Couldn\'t delete self-subscription.'));
        }

        if (Event::handle('StartUnsubscribe', array($subscriber, $other))) {

            $sub = Subscription::pkeyGet(array('subscriber' => $subscriber->id,
                                               'subscribed' => $other->id));

            // note we checked for existence above

            assert(!empty($sub));

            $result = $sub->delete();

            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                throw new Exception(_('Couldn\'t delete subscription.'));
            }

            self::blow('user:notices_with_friends:%d', $subscriber->id);

            $subscriber->blowSubscriptionsCount();
            $other->blowSubscribersCount();

            Event::handle('EndUnsubscribe', array($subscriber, $other));
        }

        return;
    }

    function exists($subscriber, $other)
    {
        $sub = Subscription::pkeyGet(array('subscriber' => $subscriber->id,
                                           'subscribed' => $other->id));
        return (empty($sub)) ? false : true;
    }
}
