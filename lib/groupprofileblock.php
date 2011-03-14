<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Profile block to show for a group
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Profile block to show for a group
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class GroupProfileBlock extends ProfileBlock
{
    protected $group = null;

    function __construct($out, $group)
    {
        parent::__construct($out);
        $this->group = $group;
    }

    function avatar()
    {
        return ($this->group->homepage_logo) ?
          $this->group->homepage_logo : User_group::defaultLogo(AVATAR_PROFILE_SIZE);
    }

    function name()
    {
        return $this->group->getBestName();
    }

    function url()
    {
        return $this->group->mainpage;
    }

    function canEdit()
    {
        $user = common_current_user();
        return ((!empty($user)) && ($user->isAdmin($this->group)));
    }

    function editUrl()
    {
        return common_local_url('editgroup', array('nickname' => $this->group->nickname));
    }

    function editText()
    {
        return _('Edit');
    }

    function location()
    {
        return $this->group->location;
    }

    function homepage()
    {
        return $this->group->homepage;
    }

    function description()
    {
        return $this->group->description;
    }
}
