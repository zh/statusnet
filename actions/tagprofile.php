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

require_once INSTALLDIR . '/lib/settingsaction.php';
require_once INSTALLDIR . '/lib/peopletags.php';

class TagprofileAction extends Action
{
    var $profile = null;
    var $error = null;

    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            common_set_returnto($_SERVER['REQUEST_URI']);
            if (Event::handle('RedirectToLogin', array($this, null))) {
                common_redirect(common_local_url('login'), 303);
            }
        }

        $id = $this->trimmed('id');
        if (!$id) {
            $this->profile = false;
        } else {
            $this->profile = Profile::staticGet('id', $id);

            if (!$this->profile) {
                $this->clientError(_('No profile with that ID.'));
                return false;
            }
        }

        $current = common_current_user()->getProfile();
        if ($this->profile && !$current->canTag($this->profile)) {
            $this->clientError(_('You cannot tag this user.'));
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if (Event::handle('StartTagProfileAction', array($this, $this->profile))) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $this->saveTags();
            } else {
                $this->showForm();
            }
            Event::handle('EndTagProfileAction', array($this, $this->profile));
        }
    }

    function title()
    {
        if (!$this->profile) {
            return _('Tag a profile');
        }
        return sprintf(_('Tag %s'), $this->profile->nickname);
    }

    function showForm($error=null)
    {
        $this->error = $error;
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Error'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', 'error', $error);
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    function showContent()
    {
        if (Event::handle('StartShowTagProfileForm', array($this, $this->profile)) && $this->profile) {
            $this->elementStart('div', 'entity_profile vcard author');
            $this->element('h2', null, _('User profile'));

            $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
            $this->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE),
                                        'class' => 'photo avatar entity_depiction',
                                        'width' => AVATAR_PROFILE_SIZE,
                                        'height' => AVATAR_PROFILE_SIZE,
                                        'alt' =>
                                        ($this->profile->fullname) ? $this->profile->fullname :
                                        $this->profile->nickname));

            $this->element('a', array('href' => $this->profile->profileurl,
                                      'class' => 'entity_nickname nickname'),
                           $this->profile->nickname);
            if ($this->profile->fullname) {
                $this->element('div', 'fn entity_fn', $this->profile->fullname);
            }

            if ($this->profile->location) {
                $this->element('div', 'label entity_location', $this->profile->location);
            }

            if ($this->profile->homepage) {
                $this->element('a', array('href' => $this->profile->homepage,
                                          'rel' => 'me',
                                          'class' => 'url entity_url'),
                               $this->profile->homepage);
            }

            if ($this->profile->bio) {
                $this->element('div', 'note entity_note', $this->profile->bio);
            }

            $this->elementEnd('div');

            $this->elementStart('form', array('method' => 'post',
                                               'id' => 'form_tag_user',
                                               'class' => 'form_settings',
                                               'name' => 'tagprofile',
                                               'action' => common_local_url('tagprofile', array('id' => $this->profile->id))));

            $this->elementStart('fieldset');
            $this->element('legend', null, _('Tag user'));
            $this->hidden('token', common_session_token());
            $this->hidden('id', $this->profile->id);

            $user = common_current_user();

            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');

            $tags = Profile_tag::getTagsArray($user->id, $this->profile->id, $user->id);
            $this->input('tags', _('Tags'),
                         ($this->arg('tags')) ? $this->arg('tags') : implode(' ', $tags),
                         _('Tags for this user (letters, numbers, -, ., and _), comma- or space- separated'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->submit('save', _('Save'));
            $this->elementEnd('fieldset');
            $this->elementEnd('form');

            Event::handle('EndShowTagProfileForm', array($this, $this->profile));
        }
    }

    function saveTags()
    {
        $id = $this->trimmed('id');
        $tagstring = $this->trimmed('tags');
        $token = $this->trimmed('token');

        if (Event::handle('StartSavePeopletags', array($this, $tagstring))) {
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }

            $tags = array();
            $tag_priv = array();

            if (is_string($tagstring) && strlen($tagstring) > 0) {

                $tags = preg_split('/[\s,]+/', $tagstring);

                foreach ($tags as &$tag) {
                    $private = @$tag[0] === '.';

                    $tag = common_canonical_tag($tag);
                    if (!common_valid_profile_tag($tag)) {
                        $this->showForm(sprintf(_('Invalid tag: "%s"'), $tag));
                        return;
                    }

                    $tag_priv[$tag] = $private;
                }
            }

            $user = common_current_user();

            try {
                $result = Profile_tag::setTags($user->id, $this->profile->id, $tags, $tag_priv);
                if (!$result) {
                    throw new Exception('The tags could not be saved.');
                }
            } catch (Exception $e) {
                $this->showForm($e->getMessage());
                return false;
            }

            if ($this->boolean('ajax')) {
                $this->startHTML('text/xml;charset=utf-8');
                $this->elementStart('head');
                $this->element('title', null, _('Tags'));
                $this->elementEnd('head');
                $this->elementStart('body');

                if ($user->id == $this->profile->id) {
                    $widget = new SelftagsWidget($this, $user, $this->profile);
                    $widget->show();
                } else {
                    $widget = new PeopletagsWidget($this, $user, $this->profile);
                    $widget->show();
                }

                $this->elementEnd('body');
                $this->elementEnd('html');
            } else {
                $this->error = 'Tags saved.';
                $this->showForm();
            }

            Event::handle('EndSavePeopletags', array($this, $tagstring));
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $this->elementStart('div', 'instructions');
            $this->element('p', null,
                           _('Use this form to add tags to your subscribers or subscriptions.'));
            $this->elementEnd('div');
        }
    }
}

