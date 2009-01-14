<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for all actions (~views)
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
 * @category  Action
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for all actions
 *
 * This is the base class for all actions in the package. An action is
 * more or less a "view" in an MVC framework.
 *
 * Actions are responsible for extracting and validating parameters; using
 * model classes to read and write to the database; and doing ouput.
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      HTMLOutputter
 */

class PublicGroupNav
{
    var $action = null;

    function __construct($action=null)
    {
        $this->action = $action;
    }

    function show()
    {
        $this->action->elementStart('ul', array('id' => 'nav_views'));

        common_menu_item(common_local_url('public'), _('Public'),
            _('Public timeline'), $this->action == 'public');

        common_menu_item(common_local_url('tag'), _('Recent tags'),
            _('Recent tags'), $this->action == 'tag');

        if (count(common_config('nickname', 'featured')) > 0) {
            common_menu_item(common_local_url('featured'), _('Featured'),
                _('Featured users'), $this->action == 'featured');
        }

        common_menu_item(common_local_url('favorited'), _('Popular'),
            _("Popular notices"), $this->action == 'favorited');

        common_element_end('ul');
    }
}
