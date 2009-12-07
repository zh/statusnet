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
 * @author    Craig Andrews <candrews@integralblue.com>, Brion Vibber <brion@status.net>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class RequireValidatedEmailPlugin extends Plugin
{
    // Users created before this time will be grandfathered in
    // without the validation requirement.
    public $grandfatherCutoff=null;

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Event handler for notice saves; rejects the notice
     * if user's address isn't validated.
     *
     * @param Notice $notice
     * @return bool hook result code
     */
    function onStartNoticeSave($notice)
    {
        $user = User::staticGet('id', $notice->profile_id);
        if (!empty($user)) { // it's a remote notice
            if (!$this->validated($user)) {
                throw new ClientException(_("You must validate your email address before posting."));
            }
        }
        return true;
    }

    /**
     * Check if a user has a validated email address or has been
     * otherwise grandfathered in.
     *
     * @param User $user
     * @return bool
     */
    protected function validated($user)
    {
        if ($this->grandfathered($user)) {
            return true;
        }

        // The email field is only stored after validation...
        // Until then you'll find them in confirm_address.
        return !empty($user->email);
    }

    /**
     * Check if a user was created before the grandfathering cutoff.
     * If so, we won't need to check for validation.
     *
     * @param User $user
     * @return bool
     */
    protected function grandfathered($user)
    {
        if ($this->grandfatherCutoff) {
            $created = strtotime($user->created . " GMT");
            $cutoff = strtotime($this->grandfatherCutoff);
            if ($created < $cutoff) {
                return true;
            }
        }
        return false;
    }
}

