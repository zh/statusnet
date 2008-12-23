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

define('PROFILES_PER_PAGE', 20);

class ProfileList
{

    var $profile = null;
    var $owner = null;
    var $action = null;

    function __construct($profile, $owner=null, $action=null)
    {
        $this->profile = $profile;
        $this->owner = $owner;
        $this->action = $action;
    }

    function show_list()
    {

        common_element_start('ul', array('id' => 'profiles', 'class' => 'profile_list'));

        $cnt = 0;

        while ($this->profile->fetch()) {
            $cnt++;
            if($cnt > PROFILES_PER_PAGE) {
                break;
            }
            $this->show();
        }

        common_element_end('ul');

        return $cnt;
    }

    function show()
    {

        common_element_start('li', array('class' => 'profile_single',
                                         'id' => 'profile-' . $this->profile->id));

        $user = common_current_user();

        if ($user && $user->id != $this->profile->id) {
            # XXX: special-case for user looking at own
            # subscriptions page
            if ($user->isSubscribed($this->profile)) {
                common_unsubscribe_form($this->profile);
            } else {
                common_subscribe_form($this->profile);
            }
        }

        $avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);
        common_element_start('a', array('href' => $this->profile->profileurl));
        common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
                                    'class' => 'avatar stream',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($this->profile->fullname) ? $this->profile->fullname :
                                    $this->profile->nickname));
        common_element_end('a');
        common_element_start('p');
        common_element_start('a', array('href' => $this->profile->profileurl,
                                        'class' => 'nickname'));
        common_raw($this->highlight($this->profile->nickname));
        common_element_end('a');
        if ($this->profile->fullname) {
            common_text(' | ');
            common_element_start('span', 'fullname');
            common_raw($this->highlight($this->profile->fullname));
            common_element_end('span');
        }
        if ($this->profile->location) {
            common_text(' | ');
            common_element_start('span', 'location');
            common_raw($this->highlight($this->profile->location));
            common_element_end('span');
        }
        common_element_end('p');
        if ($this->profile->homepage) {
            common_element_start('p', 'website');
            common_element_start('a', array('href' => $this->profile->homepage));
            common_raw($this->highlight($this->profile->homepage));
            common_element_end('a');
            common_element_end('p');
        }
        if ($this->profile->bio) {
            common_element_start('p', 'bio');
            common_raw($this->highlight($this->profile->bio));
            common_element_end('p');
        }

        # If we're on a list with an owner (subscriptions or subscribers)...

        if ($this->owner) {
            # Get tags
            $tags = Profile_tag::getTags($this->owner->id, $this->profile->id);

            common_element_start('div', 'tags_user');
            common_element_start('dl');
            common_element_start('dt');
            if ($user->id == $this->owner->id) {
                common_element('a', array('href' => common_local_url('tagother',
                                                                     array('id' => $this->profile->id))),
                               _('Tags'));
            } else {
                common_text(_('Tags'));
            }
            common_text(":");
            common_element_end('dt');
            common_element_start('dd');
            if ($tags) {
                common_element_start('ul', 'tags xoxo');
                foreach ($tags as $tag) {
                    common_element_start('li');
                    common_element('a', array('rel' => 'tag',
                                              'href' => common_local_url($this->action,
                                                                         array('nickname' => $this->owner->nickname,
                                                                               'tag' => $tag))),
                                   $tag);
                    common_element_end('li');
                }
                common_element_end('ul');
            } else {
                common_text(_('(none)'));
            }
            common_element_end('dd');
            common_element_end('dl');
            common_element_end('div');
        }

        if ($user && $user->id == $this->owner->id) {
            $this->show_owner_controls($this->profile);
        }

        common_element_end('li');
    }

    /* Override this in subclasses. */

    function show_owner_controls($profile)
    {
        return;
    }

    function highlight($text)
    {
        return htmlspecialchars($text);
    }
}