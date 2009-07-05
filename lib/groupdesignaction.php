<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for actions that use the current user's design
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
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for actions that use a group's design
 *
 * Pages related to groups can be themed with a design.
 * This superclass returns that design.
 *
 * @category Action
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 */
class GroupDesignAction extends Action {

    /** The group in question */
    var $group = null;

    /**
      * Show the groups's design stylesheet
      *
      * @return nothing
      */
     function showStylesheets()
     {
         parent::showStylesheets();

         $user = common_current_user();

         if (empty($user) || $user->viewdesigns) {
             $design = $this->getDesign();

             if (!empty($design)) {
                 $design->showCSS($this);
             }
         }
     }

    /**
     * A design for this action
     *
     * if the group attribute has been set, returns that group's
     * design.
     *
     * @return Design a design object to use
     */

    function getDesign()
    {
        if (empty($this->group)) {
            return null;
        }

        return $this->group->getDesign();
    }
}
