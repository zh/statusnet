<?php

/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Widget to show a list of groups
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

define('GROUPS_PER_PAGE', 20);

/**
 * Widget to show a list of groups
 *
 * @category Public
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class GroupList extends Widget
{
    /** Current group, group query. */
    var $group = null;
    /** Owner of this list */
    var $owner = null;
    /** Action object using us. */
    var $action = null;

    function __construct($group, $owner=null, $action=null)
    {
        parent::__construct($action);

        $this->group = $group;
        $this->owner = $owner;
        $this->action = $action;
    }

    function show()
    {
        $this->out->elementStart('ul', 'profiles groups xoxo');

        $cnt = 0;

        while ($this->group->fetch()) {
            $cnt++;
            if($cnt > GROUPS_PER_PAGE) {
                break;
            }
            $this->showgroup();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showGroup()
    {
        $this->out->elementStart('li', array('class' => 'profile',
                                             'id' => 'group-' . $this->group->id));

        $user = common_current_user();

        $this->out->elementStart('div', 'entity_profile vcard');

        $logo = ($this->group->stream_logo) ?
          $this->group->stream_logo : User_group::defaultLogo(AVATAR_STREAM_SIZE);

        $this->out->elementStart('a', array('href' => $this->group->homeUrl(),
                                            'class' => 'url',
                                            'rel' => 'group'));
        $this->out->element('img', array('src' => $logo,
                                         'class' => 'photo avatar',
                                         'width' => AVATAR_STREAM_SIZE,
                                         'height' => AVATAR_STREAM_SIZE,
                                         'alt' =>
                                         ($this->group->fullname) ? $this->group->fullname :
                                         $this->group->nickname));
        $hasFN = ($this->group->fullname) ? 'nickname url uid' : 'fn org nickname url uid';
        $this->out->elementStart('span', $hasFN);
        $this->out->raw($this->highlight($this->group->nickname));
        $this->out->elementEnd('span');
        $this->out->elementEnd('a');

        if ($this->group->fullname) {
            $this->out->elementStart('dl', 'entity_fn');
            $this->out->element('dt', null, 'Full name');
            $this->out->elementStart('dd');
            $this->out->elementStart('span', 'fn org');
            $this->out->raw($this->highlight($this->group->fullname));
            $this->out->elementEnd('span');
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if ($this->group->location) {
            $this->out->elementStart('dl', 'entity_location');
            $this->out->element('dt', null, _('Location'));
            $this->out->elementStart('dd', 'label');
            $this->out->raw($this->highlight($this->group->location));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if ($this->group->homepage) {
            $this->out->elementStart('dl', 'entity_url');
            $this->out->element('dt', null, _('URL'));
            $this->out->elementStart('dd');
            $this->out->elementStart('a', array('href' => $this->group->homepage,
                                                'class' => 'url'));
            $this->out->raw($this->highlight($this->group->homepage));
            $this->out->elementEnd('a');
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
        if ($this->group->description) {
            $this->out->elementStart('dl', 'entity_note');
            $this->out->element('dt', null, _('Note'));
            $this->out->elementStart('dd', 'note');
            $this->out->raw($this->highlight($this->group->description));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }

        # If we're on a list with an owner (subscriptions or subscribers)...

        if (!empty($user) && !empty($this->owner) && $user->id == $this->owner->id) {
            $this->showOwnerControls();
        }

        $this->out->elementEnd('div');

        if ($user) {
            $this->out->elementStart('div', 'entity_actions');
            $this->out->elementStart('ul');
            $this->out->elementStart('li', 'entity_subscribe');
            # XXX: special-case for user looking at own
            # subscriptions page
            if ($user->isMember($this->group)) {
                $lf = new LeaveForm($this->out, $this->group);
                $lf->show();
            } else if (!Group_block::isBlocked($this->group, $user->getProfile())) {
                $jf = new JoinForm($this->out, $this->group);
                $jf->show();
            }
            $this->out->elementEnd('li');
            $this->out->elementEnd('ul');
            $this->out->elementEnd('div');
        }

        $this->out->elementEnd('li');
    }

    /* Override this in subclasses. */

    function showOwnerControls()
    {
        return;
    }

    function highlight($text)
    {
        return htmlspecialchars($text);
    }
}
