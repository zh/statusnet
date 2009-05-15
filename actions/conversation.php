<?php
/**
 * Display a conversation in the browser
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/lib/noticelist.php');

/**
 * Conversation tree in the browser
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */

class ConversationAction extends Action
{
    var $id = null;
    var $page = null;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if id not passed in
     */

    function prepare($args)
    {
        parent::prepare($args);
        $this->id = $this->trimmed('id');
        if (empty($this->id)) {
            return false;
        }
        $this->page = $this->trimmed('page');
        if (empty($this->page)) {
            $this->page = 1;
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        return _("Conversation");
    }

    function showContent()
    {
        // FIXME this needs to be a tree, not a list

        $qry = 'SELECT * FROM notice WHERE conversation = %s ';

        $offset = ($this->page-1)*NOTICES_PER_PAGE;
        $limit  = NOTICES_PER_PAGE + 1;

        $txt = sprintf($qry, $this->id);

        $notices = Notice::getStream($txt,
                                     'notice:conversation:'.$this->id,
                                     $offset, $limit);

        $ct = new ConversationTree($notices, $this);

        $cnt = $ct->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'conversation', array('id' => $this->id));
    }

}

class ConversationTree extends NoticeList
{
    var $tree = null;
    var $table = null;

    function show()
    {
        $cnt = 0;

        $this->tree = array();
        $table = array();

        while ($this->notice->fetch()) {
            $cnt++;
            $this->table[$this->notice->id] = clone($this->notice);
            if (is_null($notice->reply_to)) {
                // We assume no notice has -1 ID
                $this->tree[-1] = array($notice->id);
            } else if (array_key_exists($notice->reply_to, $this->tree)) {
                $this->tree[$notice->reply_to][] = $notice->id;
            } else {
                $this->tree[$notice->reply_to] = array($notice->id);
            }
        }

        $this->out->elementStart('div', array('id' =>'notices_primary'));
        $this->out->element('h2', null, _('Notices'));
        $this->out->elementStart('ul', array('class' => 'notices'));

        if (array_key_exists(-1, $this->tree)) {
            $this->showNoticePlus($this->tree[-1][0]);
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');

        return $cnt;
    }

    function showNoticePlus($id)
    {
        $notice = $this->table[$id];

        print_r($notice);

        // We take responsibility for doing the li

        $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'notice-' . $this->notice->id));

        $item = $this->newListItem($notice);
        $item->show();

        if (array_key_exists($id, $this->tree)) {
            $children = $this->tree[$id];

            $this->out->elementStart('ul', array('class' => 'notices'));

            foreach ($children as $child) {
                $this->showNoticePlus($child);
            }

            $this->out->elementEnd('ul');
        }

        $this->out->elementEnd('li');
    }

    function newListItem($notice)
    {
        return new ConversationTreeItem($notice, $this->out);
    }
}

class ConversationTreeItem extends NoticeListItem
{
    function showStart()
    {
        // skip; ConversationTree draws the list
    }

    function showEnd()
    {
        // skip; ConversationTree draws the list
    }

    function showContext()
    {
        // skip; this _is_ the context!
    }
}