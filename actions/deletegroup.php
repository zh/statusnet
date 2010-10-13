<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Delete a group
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Delete a group
 *
 * This is the action for deleting a group.
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @fixme merge more of this code with related variants
 */

class DeletegroupAction extends RedirectingAction
{
    var $group = null;

    /**
     * Prepare to run
     *
     * @fixme merge common setup code with other group actions
     * @fixme allow group admins to delete their own groups
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to delete a group.'));
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
        if (!$cur->hasRight(Right::DELETEGROUP)) {
            $this->clientError(_('You are not allowed to delete this group.'), 403);
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On POST, delete the group.
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('no')) {
                $this->returnToPrevious();
                return;
            } elseif ($this->arg('yes')) {
                $this->handlePost();
                return;
            }
        }
        $this->showPage();
    }

    function handlePost()
    {
        $cur = common_current_user();

        try {
            if (Event::handle('StartDeleteGroup', array($this->group))) {
                $this->group->delete();
                Event::handle('EndDeleteGroup', array($this->group));
            }
        } catch (Exception $e) {
            $this->serverError(sprintf(_('Could not delete group %2$s.'),
                                       $this->group->nickname));
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, sprintf(_('Deleted group %2$s'),
                                                  $this->group->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            // @fixme add a sensible AJAX response form!
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            // @fixme if we could direct to the page on which this group
            // would have shown... that would be awesome
            common_redirect(common_local_url('groups'),
                            303);
        }
    }

    function title() {
        return _('Delete group');
    }

    function showContent() {
        $this->areYouSureForm();
    }

    /**
     * Confirm with user.
     * Ripped from DeleteuserAction
     *
     * Shows a confirmation form.
     *
     * @fixme refactor common code for things like this
     * @return void
     */
    function areYouSureForm()
    {
        $id = $this->group->id;
        $this->elementStart('form', array('id' => 'deletegroup-' . $id,
                                           'method' => 'post',
                                           'class' => 'form_settings form_entity_block',
                                           'action' => common_local_url('deletegroup', array('id' => $this->group->id))));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->element('legend', _('Delete group'));
        if (Event::handle('StartDeleteGroupForm', array($this, $this->group))) {
            $this->element('p', null,
                           _('Are you sure you want to delete this group? '.
                             'This will clear all data about the group from the '.
                             'database, without a backup. ' .
                             'Public posts to this group will still appear in ' .
                             'individual timelines.'));
            foreach ($this->args as $k => $v) {
                if (substr($k, 0, 9) == 'returnto-') {
                    $this->hidden($k, $v);
                }
            }
            Event::handle('EndDeleteGroupForm', array($this, $this->group));
        }
        $this->submit('form_action-no',
                      // TRANS: Button label on the delete group form.
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'no',
                      // TRANS: Submit button title for 'No' when deleting a group.
                      _('Do not delete this group'));
        $this->submit('form_action-yes',
                      // TRANS: Button label on the delete group form.
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'yes',
                      // TRANS: Submit button title for 'Yes' when deleting a group.
                      _('Delete this group'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }
}