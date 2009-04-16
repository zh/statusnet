<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Common parent of Personal and Profile actions
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
 * @category  Personal
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/profileminilist.php';
require_once INSTALLDIR.'/lib/groupminilist.php';

/**
 * Profile action common superclass
 *
 * Abstracts out common code from profile and personal tabs
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ProfileAction extends Action
{
    var $user = null;
    var $page = null;
    var $profile = null;

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url($this->trimmed('action'), $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_set_returnto($this->selfUrl());

        return true;
    }

    function showSections()
    {
        $this->showSubscriptions();
        $this->showSubscribers();
        $this->showGroups();
        $this->showStatistics();
    }

    function showSubscriptions()
    {
        $profile = $this->user->getSubscriptions(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscriptions',
                                         'class' => 'section'));

        $this->element('h2', null, _('Subscriptions'));

        if ($profile) {
            $pml = new ProfileMiniList($profile, $this->user, $this);
            $cnt = $pml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > PROFILES_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('subscriptions',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All subscriptions'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->user->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));

        $this->element('h2', null, _('Subscribers'));

        if ($profile) {
            $pml = new ProfileMiniList($profile, $this->user, $this);
            $cnt = $pml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > PROFILES_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('subscribers',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All subscribers'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function showStatistics()
    {
        // XXX: WORM cache this
        $subs = new Subscription();
        $subs->subscriber = $this->profile->id;
        $subs_count = (int) $subs->count() - 1;

        $subbed = new Subscription();
        $subbed->subscribed = $this->profile->id;
        $subbed_count = (int) $subbed->count() - 1;

        $notices = new Notice();
        $notices->profile_id = $this->profile->id;
        $notice_count = (int) $notices->count();

        $this->elementStart('div', array('id' => 'entity_statistics',
                                         'class' => 'section'));

        $this->element('h2', null, _('Statistics'));

        // Other stats...?
        $this->elementStart('dl', 'entity_user-id');
        $this->element('dt', null, _('User ID'));
        $this->element('dd', null, $this->profile->id);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_member-since');
        $this->element('dt', null, _('Member since'));
        $this->element('dd', null, date('j M Y',
                                        strtotime($this->profile->created)));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscriptions');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscriptions',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscriptions'));
        $this->elementEnd('dt');
        $this->element('dd', null, (is_int($subs_count)) ? $subs_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscribers');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscribers',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscribers'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscribers', (is_int($subbed_count)) ? $subbed_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_notices');
        $this->element('dt', null, _('Notices'));
        $this->element('dd', null, (is_int($notice_count)) ? $notice_count : '0');
        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

    function showGroups()
    {
        $groups = $this->user->getGroups(0, GROUPS_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_groups',
                                         'class' => 'section'));

        $this->element('h2', null, _('Groups'));

        if ($groups) {
            $gml = new GroupMiniList($groups, $this->user, $this);
            $cnt = $gml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > GROUPS_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('usergroups',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All groups'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }
}