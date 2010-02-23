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

/* Subscribe user $user to other user $other.
 * Note: $other must be a local user, not a remote profile.
 * Because the other way is quite a bit more complicated.
 */

function subs_subscribe_to($user, $other)
{
    try {
        Subscription::start($user->getProfile(), $other);
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function subs_unsubscribe_to($user, $other)
{
    try {
        Subscription::cancel($user->getProfile(), $other);
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function subs_unsubscribe_from($user, $other){
   $local = User::staticGet("nickname",$other);
   if($local){
     return subs_unsubscribe_to($local,$user);
   } else {
    try {
        $remote = Profile::staticGet("nickname",$other);
        if(is_string($remote)){
          return $remote;
        }
        if (Event::handle('StartUnsubscribe', array($remote,$user))) {

            $sub = DB_DataObject::factory('subscription');

            $sub->subscriber = $remote->id;
            $sub->subscribed = $user->id;

            $sub->find(true);

            // note we checked for existence above

            if (!$sub->delete())
              return _('Couldn\'t delete subscription.');

            $cache = common_memcache();

            if ($cache) {
                $cache->delete(common_cache_key('user:notices_with_friends:' . $remote->id));
            }


            $user->blowSubscribersCount();
            $remote->blowSubscribersCount();

            Event::handle('EndUnsubscribe', array($remote, $user));
        }
    } catch (Exception $e) {
        return $e->getMessage();
    }
   }
}

