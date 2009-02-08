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

    function showFeeds()
    {
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('allrss', array('nickname' =>
                                                                                $this->user->nickname)),
                                     'type' => 'application/rss+xml',
                                     'title' => sprintf(_('Feed for friends of %s'), $this->user->nickname)));
    }

    /**
     * Output document relationship links
     *
     * @return void
     */
    function showRelationshipLinks()
    {
        // Machine-readable pagination
        if ($this->page > 1) {
            $this->element('link', array('rel' => 'next',
                                         'href' => common_local_url('all',
                                                                    array('nickname' => $this->user->nickname,
                                                                          'page' => $this->page - 1)),
                                         'title' => _('Next Notices')));
        }
        $this->element('link', array('rel' => 'prev',
                                     'href' => common_local_url('all',
                                                                array('nickname' => $this->user->nickname,
                                                                      'page' => $this->page + 1)),
                                     'title' => _('Previous Notices')));
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showExportData()
    {
        $fl = new FeedList($this);
        $fl->show(array(0=>array('href'=>common_local_url('allrss', array('nickname' => $this->user->nickname)),
                                 'type' => 'rss',
                                 'version' => 'RSS 1.0',
                                 'item' => 'allrss')));
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
