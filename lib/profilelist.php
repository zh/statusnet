<?php

/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Widget to show a list of profiles
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Public
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Widget to show a list of profiles
 *
 * @category Public
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ProfileList extends Widget
{
    /** Current profile, profile query. */
    var $profile = null;
    /** Owner of this list */
    var $owner = null;
    /** Action object using us. */
    var $action = null;

    function __construct($profile, $owner=null, $action=null)
    {
        parent::__construct($action);

        $this->profile = $profile;
        $this->owner = $owner;
        $this->action = $action;
    }

    function show()
    {

        $this->out->elementStart('ul', 'profiles');

        $cnt = 0;

        while ($this->profile->fetch()) {
            $cnt++;
            if($cnt > PROFILES_PER_PAGE) {
                break;
            }
            $this->showProfile();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showProfile()
    {
        $this->out->elementStart('li', array('class' => 'profile',
                                             'id' => 'profile-' . $this->profile->id));

        $user = common_current_user();
        $is_own = !is_null($user) && isset($this->owner) && ($user->id === $this->owner->id);

        $this->out->elementStart('div', 'entity_profile vcard');

        $avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);
        $this->out->elementStart('a', array('href' => $this->profile->profileurl,
                                            'class' => 'url'));
        $this->out->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_STREAM_SIZE),
                                         'class' => 'photo avatar',
                                         'width' => AVATAR_STREAM_SIZE,
                                         'height' => AVATAR_STREAM_SIZE,
                                         'alt' =>
                                         ($this->profile->fullname) ? $this->profile->fullname :
                                         $this->profile->nickname));
        $hasFN = ($this->profile->fullname !== '') ? 'nickname' : 'fn nickname';
        $this->out->elementStart('span', $hasFN);
        $this->out->raw($this->highlight($this->profile->nickname));
        $this->out->elementEnd('span');
        $this->out->elementEnd('a');

        if (!empty($this->profile->fullname)) {
            $this->out->elementStart('dl', 'entity_fn');
            $this->out->element('dt', null, 'Full name');
            $this->out->elementStart('dd');
            $this->out->elementStart('span', 'fn');
            $this->out->raw($this->highlight($this->profile->fullname));
            $this->out->elementEnd('span');
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if (!empty($this->profile->location)) {
            $this->out->elementStart('dl', 'entity_location');
            $this->out->element('dt', null, _('Location'));
            $this->out->elementStart('dd', 'label');
            $this->out->raw($this->highlight($this->profile->location));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if (!empty($this->profile->homepage)) {
            $this->out->elementStart('dl', 'entity_url');
            $this->out->element('dt', null, _('URL'));
            $this->out->elementStart('dd');
            $this->out->elementStart('a', array('href' => $this->profile->homepage,
                                                'class' => 'url'));
            $this->out->raw($this->highlight($this->profile->homepage));
            $this->out->elementEnd('a');
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if (!empty($this->profile->bio)) {
            $this->out->elementStart('dl', 'entity_note');
            $this->out->element('dt', null, _('Note'));
            $this->out->elementStart('dd', 'note');
            $this->out->raw($this->highlight($this->profile->bio));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }

        # If we're on a list with an owner (subscriptions or subscribers)...

        if ($this->owner) {
            # Get tags
            $tags = Profile_tag::getTags($this->owner->id, $this->profile->id);

            $this->out->elementStart('dl', 'entity_tags');
            $this->out->elementStart('dt');
            if ($is_own) {
                $this->out->element('a', array('href' => common_local_url('tagother',
                                                                          array('id' => $this->profile->id))),
                                    _('Tags'));
            } else {
                $this->out->text(_('Tags'));
            }
            $this->out->elementEnd('dt');
            $this->out->elementStart('dd');
            if ($tags) {
                $this->out->elementStart('ul', 'tags xoxo');
                foreach ($tags as $tag) {
                    $this->out->elementStart('li');
                    $this->out->element('span', 'mark_hash', '#');
                    $this->out->element('a', array('rel' => 'tag',
                                                   'href' => common_local_url($this->action->trimmed('action'),
                                                                              array('nickname' => $this->owner->nickname,
                                                                                    'tag' => $tag))),
                                        $tag);
                    $this->out->elementEnd('li');
                }
                $this->out->elementEnd('ul');
            } else {
                $this->out->text(_('(none)'));
            }
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }

        if ($is_own) {
            $this->showOwnerControls($this->profile);
        }

        $this->out->elementEnd('div');

        $this->out->elementStart('div', 'entity_actions');

        $this->out->elementStart('ul');

        // Is this a logged-in user, looking at someone else's
        // profile?

        if (!empty($user) && $this->profile->id != $user->id) {
            $this->out->elementStart('li', 'entity_subscribe');
            if ($user->isSubscribed($this->profile)) {
                $usf = new UnsubscribeForm($this->out, $this->profile);
                $usf->show();
            } else {
                $sf = new SubscribeForm($this->out, $this->profile);
                $sf->show();
            }
            $this->out->elementEnd('li');
            $this->out->elementStart('li', 'entity_block');
            if ($user->id == $this->owner->id) {
                $this->showBlockForm();
            }
            $this->out->elementEnd('li');
        }

        $this->out->elementEnd('ul');

        $this->out->elementEnd('div');

        $this->out->elementEnd('li');
    }

    /* Override this in subclasses. */

    function showOwnerControls($profile)
    {
        return;
    }

    function highlight($text)
    {
        return htmlspecialchars($text);
    }

    function showBlockForm()
    {
    }
}
