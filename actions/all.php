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

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

class AllAction extends Action
{
    var $user = null;
    var $page = null;

    function isReadOnly()
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $nickname = common_canonical_nickname($this->arg('nickname'));
        $this->user = User::staticGet('nickname', $nickname);
        $this->page = $this->trimmed('page');
        if (!$this->page) {
            $this->page = 1;
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!$this->user) {
            $this->clientError(_('No such user.'));
            return;
        }

        $this->showPage();
    }

    function title()
    {
        if ($this->page > 1) {
            return sprintf(_("%s and friends, page %d"), $this->user->nickname, $this->page);
        } else {
            return sprintf(_("%s and friends"), $this->user->nickname);
        }
    }

    function getFeeds()
    {
        return array(new Feed(Feed::RSS1,
                              common_local_url('allrss', array('nickname' =>
                                                               $this->user->nickname)),
                              sprintf(_('Feed for friends of %s (RSS 1.0)'), $this->user->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('api', array('apiaction' => 'statuses',
                                                            'method' => 'friends_timeline',
                                                            'argument' => $this->user->nickname.'.rss')),
                              sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->user->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('api', array('apiaction' => 'statuses',
                                                            'method' => 'friends_timeline',
                                                            'argument' => $this->user->nickname.'.atom')),
                              sprintf(_('Feed for friends of %s (Atom)'), $this->user->nickname)));
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showContent()
    {
        $notice = $this->user->noticesWithFriends(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'all', array('nickname' => $this->user->nickname));
    }

    function showPageTitle()
    {
        $user =& common_current_user();
        if ($user && ($user->id == $this->user->id)) {
            $this->element('h1', NULL, _("You and friends"));
        } else {
            $this->element('h1', NULL, sprintf(_('%s and friends'), $this->user->nickname));
        }
    }

}
