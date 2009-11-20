<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin that requires the user to have a validated email address before they can post notices
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class RequireValidatedEmailPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onStartNoticeSave($notice)
    {
        $user = User::staticGet('id', $notice->profile_id);
        if (!empty($user)) { // it's a remote notice
            if (empty($user->email)) {
                throw new ClientException(_("You must validate your email address before posting."));
            }
        }
        return true;
    }
}

