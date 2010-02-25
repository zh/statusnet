<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Join a group
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Join a group
 *
 * This is the action for joining a group. It works more or less like the subscribe action
 * for users.
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class JoingroupAction extends Action
{
    var $group = null;

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to join a group.'));
            return false;
        }

        $nickname_arg = $this->trimmed('nickname');
        $id = intval($this->arg('id'));
        if ($id) {
            $this->group = User_group::staticGet('id', $id);
        } else if ($nickname_arg) {
            $nickname = common_canonical_nickname($nickname_arg);

            // Permanent redirect on non-canonical nickname

            if ($nickname_arg != $nickname) {
                $args = array('nickname' => $nickname);
                common_redirect(common_local_url('leavegroup', $args), 301);
                return false;
            }

            $local = Local_group::staticGet('nickname', $nickname);

            if (!$local) {
                $this->clientError(_('No such group.'), 404);
                return false;
            }

            $this->group = User_group::staticGet('id', $local->group_id);
        } else {
            $this->clientError(_('No nickname or ID.'), 404);
            return false;
        }

        if (!$this->group) {
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $cur = common_current_user();

        if ($cur->isMember($this->group)) {
            $this->clientError(_('You are already a member of that group.'), 403);
            return false;
        }

        if (Group_block::isBlocked($this->group, $cur->getProfile())) {
            $this->clientError(_('You have been blocked from that group by the admin.'), 403);
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On POST, add the current user to the group
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        $cur = common_current_user();

        try {
            if (Event::handle('StartJoinGroup', array($this->group, $cur))) {
                Group_member::join($this->group->id, $cur->id);
                Event::handle('EndJoinGroup', array($this->group, $cur));
            }
        } catch (Exception $e) {
            $this->serverError(sprintf(_('Could not join user %1$s to group %2$s.'),
                                       $cur->nickname, $this->group->nickname));
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, sprintf(_('%1$s joined group %2$s'),
                                                  $cur->nickname,
                                                  $this->group->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            $lf = new LeaveForm($this, $this->group);
            $lf->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('groupmembers', array('nickname' =>
                                                                   $this->group->nickname)),
                            303);
        }
    }
}