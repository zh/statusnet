<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Profile block to show for an account
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
 * Profile block to show for an account
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class AccountProfileBlock extends ProfileBlock
{
    protected $profile = null;

    function __construct($out, $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;
    }

    function avatar()
    {
        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        if (empty($avatar)) {
            $avatar = $this->profile->getAvatar(73);
        }
        return (!empty($avatar)) ? 
            $avatar->displayUrl() : 
            Avatar::defaultImage(AVATAR_PROFILE_SIZE);
    }

    function name()
    {
        return $this->profile->getBestName();
    }

    function url()
    {
        return $this->profile->profileurl;
    }

    function canEdit()
    {
        $user = common_current_user();
        return ((!empty($user)) && ($user->id == $profile->id));
    }

    function editUrl()
    {
        return common_local_url('profilesettings');
    }

    function editText()
    {
        return _('Edit');
    }

    function location()
    {
        return $this->profile->location;
    }

    function homepage()
    {
        return $this->profile->homepage;
    }

    function description()
    {
        return $this->profile->bio;
    }
}