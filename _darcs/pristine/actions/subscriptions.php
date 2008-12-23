<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/gallery.php');

class SubscriptionsAction extends GalleryAction
{

    function gallery_type()
    {
        return _('Subscriptions');
    }

    function get_instructions(&$profile)
    {
        $user =& common_current_user();
        if ($user && ($user->id == $profile->id)) {
            return _('These are the people whose notices you listen to.');
        } else {
            return sprintf(_('These are the people whose notices %s listens to.'), $profile->nickname);
        }
    }

    function fields()
    {
        return array('subscribed', 'subscriber');
    }

    function div_class()
    {
        return 'subscriptions';
    }

    function get_other(&$subs)
    {
        return $subs->subscribed;
    }

    function profile_list_class()
    {
        return 'SubscriptionsList';
    }
}

class SubscriptionsList extends ProfileList
{

    function show_owner_controls($profile)
    {

        $sub = Subscription::pkeyGet(array('subscriber' => $this->owner->id,
                                           'subscribed' => $profile->id));
        if (!$sub) {
            return;
        }

        common_element_start('form', array('id' => 'subedit-' . $profile->id,
                                           'method' => 'post',
                                           'class' => 'subedit',
                                           'action' => common_local_url('subedit')));
        common_hidden('token', common_session_token());
        common_hidden('profile', $profile->id);
        common_checkbox('jabber', _('Jabber'), $sub->jabber);
        common_checkbox('sms', _('SMS'), $sub->sms);
        common_submit('save', _('Save'));
        common_element_end('form');
        return;
    }
}
