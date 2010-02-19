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

/* Subscribe $user to nickname $other_nickname
  Returns true or an error message.
*/

function subs_subscribe_user($user, $other_nickname)
{

    $other = User::staticGet('nickname', $other_nickname);

    if (!$other) {
        return _('No such user.');
    }

    return subs_subscribe_to($user, $other);
}

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

/* Unsubscribe $user from nickname $other_nickname
  Returns true or an error message.
*/

function subs_unsubscribe_user($user, $other_nickname)
{

    $other = User::staticGet('nickname', $other_nickname);

    if (!$other) {
        return _('No such user.');
    }

    return subs_unsubscribe_to($user, $other->getProfile());
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