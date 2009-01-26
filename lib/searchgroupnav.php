<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Menu for search actions
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
 * @category  Menu
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Menu for public group of actions
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Widget
 */

class SearchGroupNav extends Widget
{
    var $action = null;
    var $q = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */

    function __construct($action=null, $q = null)
    {
        parent::__construct($action);
        $this->action = $action;
        $this->q = $q;
    }

    /**
     * Show the menu
     *
     * @return void
     */

    function show()
    {
        $action_name = $this->action->trimmed('action');
        $this->action->elementStart('ul', array('class' => 'nav'));
        $args = array();
        if ($this->q) {
            $args['q'] = $this->q;
        }
        $this->out->menuItem(common_local_url('peoplesearch', $args), _('People'),
            _('Find people on this site'), $action_name == 'peoplesearch', 'nav_search_people');
        $this->out->menuItem(common_local_url('noticesearch', $args), _('Notice'),
            _('Find content of notices'), $action_name == 'noticesearch', 'nav_search_notice');
        $this->out->menuItem(common_local_url('groupsearch', $args), _('Group'),
            _('Find groups on this site'), $action_name == 'groupsearch', 'nav_search_notice');
        $this->action->elementEnd('ul');
    }
}

