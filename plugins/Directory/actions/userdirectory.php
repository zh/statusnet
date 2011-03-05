<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Output a user directory
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
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET'))
{
    exit(1);
}

require_once INSTALLDIR . '/lib/publicgroupnav.php';

/**
 * User directory
 *
 * @category Personal
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class UserdirectoryAction extends Action
{
    /**
     * @var $page       integer  the page we're on
     */
    protected $page   = null;

    /**
     * @var $filter     string    what to filter the search results by
     */
    protected $filter = null;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // @fixme: This looks kinda gross

        if ($this->filter == 'all') {
            if ($this->page != 1) {
                return(sprintf(_m('All users, page %d'), $this->page));
            }
            return _m('All users');
        }

        if ($this->page == 1) {
            return sprintf(
                _m('Users with nicknames beginning with %s'),
                $this->filter
            );
        } else {
            return sprintf(
                _m('Users with nicknames starting with %s, page %d'),
                $this->filter,
                $this->page
            );
        }
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        return _('User directory');
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     * @todo move queries from showContent() to here
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->page   = ($this->arg('page')) ? ($this->arg('page') + 0) : 1;
        $filter       = $this->arg('filter');
        $this->filter = isset($filter) ? $filter : 'all';
        $this->sort   = $this->arg('sort');
        $this->order  = $this->boolean('asc'); // ascending or decending

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handle request
     *
     * Shows the page
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    /**
     * Local navigation
     *
     * This page is part of the public group, so show that.
     *
     * @return void
     */
    function showLocalNav()
    {
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    /**
     * Content area
     *
     * Shows the list of popular notices
     *
     * @return void
     */
    function showContent()
    {
        // XXX Need search bar

        $this->elementStart('div', array('id' => 'user_directory'));

        $alphaNav = new AlphaNav($this, true, array('All'));
        $alphaNav->show();

        // XXX Maybe use a more specialized version of ProfileList here

        $profile = $this->getUsers();
        $cnt     = 0;

        if (!empty($profile)) {
            $profileList = new SortableSubscriptionList(
                $profile,
                common_current_user(),
                $this
            );

            $cnt = $profileList->show();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }
        }

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'userdirectory',
            array('filter' => $this->filter)
        );

        $this->elementEnd('div');

    }

    /*
     * Get users filtered by the current filter and page
     */
    function getUsers()
    {

        $profile = new Profile();

        $offset = ($this->page - 1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;
        $sort   = $this->getSortKey();
        $sql    = 'SELECT profile.* FROM profile, user WHERE profile.id = user.id';

        if ($this->filter != 'all') {
            $sql .= sprintf(
                ' AND LEFT(LOWER(profile.nickname), 1) = \'%s\'',
                $this->filter
            );
        }

        $sql .= sprintf(
            ' ORDER BY profile.%s %s, profile.nickname DESC LIMIT %d, %d',
            $sort,
            ($this->order) ? 'ASC' : 'DESC',
            $offset,
            $limit
        );

        $profile->query($sql);

        return $profile;
    }

    /**
     * Filter the sort parameter
     *
     * @return string   a column name for sorting
     */
    function getSortKey()
    {
        switch ($this->sort) {
        case 'nickname':
            return $this->sort;
            break;
        case 'created':
            return $this->sort;
            break;
        default:
            return 'nickname';
        }
    }

    /**
     * Show a nice message when there's no search results
     */
    function showEmptyListMessage()
    {
        $message = sprintf(_m('No users starting with %s'), $this->filter);

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

}
