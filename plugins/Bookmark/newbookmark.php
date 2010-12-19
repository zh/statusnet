<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Add a new bookmark
 * 
 * PHP version 5
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
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Add a new bookmark
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class NewbookmarkAction extends Action
{
	private $_user        = null;
	private $_error       = null;
	private $_complete    = null;
	private $_title       = null;
	private $_url         = null;
	private $_tags        = null;
	private $_description = null;

	function title()
	{
		return _('New bookmark');
	}

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        parent::prepare($argarray);

		$this->_user = common_current_user();

		if (empty($this->_user)) {
			throw new ClientException(_("Must be logged in to post a bookmark."), 403);
		}

		if ($this->isPost()) {
			$this->checkSessionToken();
		}

		$this->_title       = $this->trimmed('title');
		$this->_url         = $this->trimmed('url');
		$this->_tags        = $this->trimmed('tags');
		$this->_description = $this->trimmed('description');

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
		parent::handle($argarray);

		if ($this->isPost()) {
			$this->newBookmark();
		} else {
			$this->showPage();
		}

        return;
    }

    /**
     * Add a new bookmark
     *
     * @return void
     */

    function newBookmark()
    {
		try {
			if (empty($this->_title)) {
				throw new ClientException(_('Bookmark must have a title.'));
			}

			if (empty($this->_url)) {
				throw new ClientException(_('Bookmark must have an URL.'));
			}


			$saved = Notice_bookmark::saveNew($this->_user,
											  $this->_title,
											  $this->_url,
											  $this->_tags,
											  $this->_description);

		} catch (ClientException $ce) {
			$this->_error = $ce->getMessage();
			$this->showPage();
			return;
		}

		common_redirect($saved->bestUrl(), 303);
    }

    /**
     * Show the bookmark form
     *
     * @return void
     */

    function showContent()
    {
		if (!empty($this->_error)) {
			$this->element('p', 'error', $this->_error);
		}

		$form = new BookmarkForm($this,
								 $this->_title,
								 $this->_url,
								 $this->_tags,
								 $this->_description);

		$form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
