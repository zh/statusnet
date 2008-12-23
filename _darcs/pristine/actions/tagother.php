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

require_once(INSTALLDIR.'/lib/settingsaction.php');

class TagotherAction extends Action {

    function handle($args)
    {

        parent::handle($args);

        if (!common_logged_in()) {
            $this->client_error(_('Not logged in'), 403);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->save_tags();
        } else {
            $id = $this->trimmed('id');
            if (!$id) {
                $this->client_error(_('No id argument.'));
                return;
            }
            $profile = Profile::staticGet('id', $id);
            if (!$profile) {
                $this->client_error(_('No profile with that ID.'));
                return;
            }
            $this->show_form($profile);
        }
    }

    function show_form($profile, $error=null)
    {

        $user = common_current_user();

        common_show_header(_('Tag a person'),
                           null, array($profile, $error), array($this, 'show_top'));

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);

        common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_PROFILE_SIZE),
                                    'class' => 'avatar stream',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' =>
                                    ($profile->fullname) ? $profile->fullname :
                                    $profile->nickname));

        common_element('a', array('href' => $profile->profileurl,
                                  'class' => 'external profile nickname'),
                       $profile->nickname);

        if ($profile->fullname) {
            common_element_start('div', 'fullname');
            if ($profile->homepage) {
                common_element('a', array('href' => $profile->homepage),
                               $profile->fullname);
            } else {
                common_text($profile->fullname);
            }
            common_element_end('div');
        }
        if ($profile->location) {
            common_element('div', 'location', $profile->location);
        }
        if ($profile->bio) {
            common_element('div', 'bio', $profile->bio);
        }

        common_element_start('form', array('method' => 'post',
                                           'id' => 'tag_user',
                                           'name' => 'tagother',
                                           'action' => $this->self_url()));
        common_hidden('token', common_session_token());
        common_hidden('id', $profile->id);
        common_input('tags', _('Tags'),
                     ($this->arg('tags')) ? $this->arg('tags') : implode(' ', Profile_tag::getTags($user->id, $profile->id)),
                     _('Tags for this user (letters, numbers, -, ., and _), comma- or space- separated'));

        common_submit('save', _('Save'));
        common_element_end('form');
        common_show_footer();

    }

    function save_tags()
    {

        $id = $this->trimmed('id');
        $tagstring = $this->trimmed('tags');
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $profile = Profile::staticGet('id', $id);

        if (!$profile) {
            $this->client_error(_('No such profile.'));
            return;
        }

        if (is_string($tagstring) && strlen($tagstring) > 0) {

            $tags = array_map('common_canonical_tag',
                              preg_split('/[\s,]+/', $tagstring));

            foreach ($tags as $tag) {
                if (!common_valid_profile_tag($tag)) {
                    $this->show_form($profile, sprintf(_('Invalid tag: "%s"'), $tag));
                    return;
                }
            }
        } else {
            $tags = array();
        }

        $user = common_current_user();

        if (!Subscription::pkeyGet(array('subscriber' => $user->id,
                                         'subscribed' => $profile->id)) &&
            !Subscription::pkeyGet(array('subscriber' => $profile->id,
                                         'subscribed' => $user->id)))
        {
            $this->client_error(_('You can only tag people you are subscribed to or who are subscribed to you.'));
            return;
        }

        $result = Profile_tag::setTags($user->id, $profile->id, $tags);

        if (!$result) {
            $this->client_error(_('Could not save tags.'));
            return;
        }

        $action = $user->isSubscribed($profile) ? 'subscriptions' : 'subscribers';

        if ($this->boolean('ajax')) {
            common_start_html('text/xml');
            common_element_start('head');
            common_element('title', null, _('Tags'));
            common_element_end('head');
            common_element_start('body');
            common_element_start('p', 'subtags');
            foreach ($tags as $tag) {
                common_element('a', array('href' => common_local_url($action,
                                                                     array('nickname' => $user->nickname,
                                                                           'tag' => $tag))),
                               $tag);
            }
            common_element_end('p');
            common_element_end('body');
            common_element_end('html');
        } else {
            common_redirect(common_local_url($action, array('nickname' =>
                                                            $user->nickname)));
        }
    }

    function show_top($arr = null)
    {
        list($profile, $error) = $arr;
        if ($error) {
            common_element('p', 'error', $error);
        } else {
            common_element_start('div', 'instructions');
            common_element('p', null,
                           _('Use this form to add tags to your subscribers or subscriptions.'));
            common_element_end('div');
        }
    }
}

