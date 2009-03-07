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
    var $notices = null;
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
        if (!$this->id) {
            return false;
        }
        $this->notices = $this->getNotices();
        $this->page = $this->trimmed('page');
        if (empty($this->page)) {
            $this->page = 1;
        }
        return true;
    }

    /**
     * Get notices
     *
     * @param integer $limit max number of notices to return
     *
     * @return array notices
     */

    function getNotices($limit=0)
    {
        $qry = 'SELECT notice.*, '.
          'FROM notice WHERE conversation = %d '.
          'ORDER BY created ';

        $offset = 0;
        $limit  = NOTICES_PER_PAGE + 1;

        if (common_config('db', 'type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        return Notice::getStream(sprintf($qry, $this->id),
                                 'notice:conversation:'.$this->id,
                                 $offset, $limit);
    }

    function handle($args)
    {
        $this->showPage();
    }

    function title()
    {
        return _("Conversation");
    }

    function showContent()
    {
        // FIXME this needs to be a tree, not a list

        $nl = new NoticeList($this->notices, $this);

        $cnt = $nl->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'conversation', array('id' => $this->id));
    }

}

