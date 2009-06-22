<?php
/**
 * Make another user an admin of a group
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Make another user an admin of a group
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */

class MakeadminAction extends Action
{
    var $profile = null;
    var $group = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return false;
        }
        $token = $this->trimmed('token');
        if (empty($token) || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        $id = $this->trimmed('profileid');
        if (empty($id)) {
            $this->clientError(_('No profile specified.'));
            return false;
        }
        $this->profile = Profile::staticGet('id', $id);
        if (empty($this->profile)) {
            $this->clientError(_('No profile with that ID.'));
            return false;
        }
        $group_id = $this->trimmed('groupid');
        if (empty($group_id)) {
            $this->clientError(_('No group specified.'));
            return false;
        }
        $this->group = User_group::staticGet('id', $group_id);
        if (empty($this->group)) {
            $this->clientError(_('No such group.'));
            return false;
        }
        $user = common_current_user();
        if (!$user->isAdmin($this->group)) {
            $this->clientError(_('Only an admin can make another user an admin.'), 401);
            return false;
        }
        if ($this->profile->isAdmin($this->group)) {
            $this->clientError(sprintf(_('%s is already an admin for group "%s".'),
                                       $this->profile->getBestName(),
                                       $this->group->getBestName()),
                               401);
            return false;
        }
        return true;
    }

    /**
     * Handle request
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->makeAdmin();
        }
    }

    /**
     * Make user an admin
     *
     * @return void
     */

    function makeAdmin()
    {
        $member = Group_member::pkeyGet(array('group_id' => $this->group->id,
                                              'profile_id' => $this->profile->id));

        if (empty($member)) {
            $this->serverError(_('Can\'t get membership record for %s in group %s'),
                               $this->profile->getBestName(),
                               $this->group->getBestName());
        }

        $orig = clone($member);

        $member->is_admin = 1;

        $result = $member->update($orig);

        if (!$result) {
            common_log_db_error($member, 'UPDATE', __FILE__);
            $this->serverError(_('Can\'t make %s an admin for group %s'),
                               $this->profile->getBestName(),
                               $this->group->getBestName());
        }

        foreach ($this->args as $k => $v) {
            if ($k == 'returnto-action') {
                $action = $v;
            } else if (substr($k, 0, 9) == 'returnto-') {
                $args[substr($k, 9)] = $v;
            }
        }

        if ($action) {
            common_redirect(common_local_url($action, $args), 303);
        } else {
            common_redirect(common_local_url('groupmembers',
                                             array('nickname' => $this->group->nickname)),
                            303);
        }
    }
}
