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

class TagAction extends Action
{
    function prepare($args)
    {
        parent::prepare($args);
        $this->tag = $this->trimmed('tag');

        if (!$this->tag) {
            common_redirect(common_local_url('publictagcloud'), 301);
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            return sprintf(_("Notices tagged with %s"), $this->tag);
        } else {
            return sprintf(_("Notices tagged with %s, page %d"),
                           $this->tag,
                           $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    function showFeeds()
    {
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('tagrss', array('tag' => $this->tag)),
                                     'type' => 'application/rss+xml',
                                     'title' => sprintf(_('Feed for tag %s'), $this->tag)));
    }

    function showPageNotice()
    {
        return sprintf(_('Messages tagged "%s", most recent first'), $this->tag);
    }

    function showExportData()
    {
        $fl = new FeedList($this);
        $fl->show(array(0=>array('href'=>common_local_url('tagrss', array('tag' => $this->tag)),
                                 'type' => 'rss',
                                 'version' => 'RSS 1.0',
                                 'item' => 'tagrss')));
    }

    function showContent()
    {
        $notice = Notice_tag::getStream($this->tag, (($this->page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'tag', array('tag' => $this->tag));
    }
}
