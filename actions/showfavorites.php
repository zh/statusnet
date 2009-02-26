<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * List of replies
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
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

/**
 * List of replies
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ShowfavoritesAction extends Action
{
    /** User we're getting the faves of */
    var $user = null;
    /** Page of the faves we're on */
    var $page = null;

    /**
     * Is this a read-only page?
     *
     * @return boolean true
     */

    function isReadOnly()
    {
        return true;
    }

    /**
     * Title of the page
     *
     * Includes name of user and page number.
     *
     * @return string title of page
     */

    function title()
    {
        if ($this->page == 1) {
            return sprintf(_("%s favorite notices"), $this->user->nickname);
        } else {
            return sprintf(_("%s favorite notices, page %d"),
                           $this->user->nickname,
                           $this->page);
        }
    }

    /**
     * Prepare the object
     *
     * Check the input values and initialize the object.
     * Shows an error page on bad input.
     *
     * @param array $args $_REQUEST data
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $nickname = common_canonical_nickname($this->arg('nickname'));

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'));
            return false;
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handle a request
     *
     * Just show the page. All args already handled.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Feeds for the <head> section
     *
     * @return array Feed objects to show
     */

    function getFeeds()
    {
        $feedurl   = common_local_url('favoritesrss',
                                      array('nickname' =>
                                            $this->user->nickname));
        $feedtitle = sprintf(_('Feed for favorites of %s'),
                             $this->user->nickname);

        return array(new Feed(Feed::RSS1, $feedurl, $feedtitle));
    }

    /**
     * show the personal group nav
     *
     * @return void
     */

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    /**
     * Show the content
     *
     * A list of notices that this user has marked as a favorite
     *
     * @return void
     */

    function showContent()
    {
        $notice = $this->user->favoriteNotices(($this->page-1)*NOTICES_PER_PAGE,
                                               NOTICES_PER_PAGE + 1);

        if (!$notice) {
            $this->serverError(_('Could not retrieve favorite notices.'));
            return;
        }

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'showfavorites',
                          array('nickname' => $this->user->nickname));
    }
}
