<?php
/**
 * Block a user action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 * Block a user action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class BlockAction extends Action
{
    var $profile = null;
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
        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        $id = $this->trimmed('blockto');
        if (!$id) {
            $this->clientError(_('No profile specified.'));
            return false;
        }
        $this->profile = Profile::staticGet('id', $id);
        if (!$this->profile) {
            $this->clientError(_('No profile with that ID.'));
            return false;
        }
        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('no')) {
                $cur = common_current_user();
                $other = Profile::staticGet('id', $this->arg('blockto'));
                common_redirect(common_local_url('showstream', array('nickname' => $other->nickname)),
                                303);
            } elseif ($this->arg('yes')) {
                $this->blockProfile();
            } elseif ($this->arg('blockto')) {
                $this->showPage();
            }
        }
    }

    function showContent() {
        $this->areYouSureForm();
    }

    function title() {
        return _('Block user');
    }

    function showNoticeForm() {
        // nop
    }

    /**
     * Confirm with user.
     *
     * Shows a confirmation form.
     *
     * @return void
     */
    function areYouSureForm()
    {
        $id = $this->profile->id;
        $this->element('p', null,
                       _('Are you sure you want to block this user? '.
                         'Afterwards, they will be unsubscribed from you, '.
                         'unable to subscribe to you in the future, and '.
                         'you will not be notified of any @-replies from them.'));
        $this->elementStart('form', array('id' => 'block-' . $id,
                                           'method' => 'post',
                                           'class' => 'block',
                                           'action' => common_local_url('block')));
        $this->hidden('token', common_session_token());
        $this->element('input', array('id' => 'blockto-' . $id,
                                      'name' => 'blockto',
                                      'type' => 'hidden',
                                      'value' => $id));
        foreach ($this->args as $k => $v) {
            if (substr($k, 0, 9) == 'returnto-') {
                $this->hidden($k, $v);
            }
        }
        $this->submit('no', _('No'));
        $this->submit('yes', _('Yes'));
        $this->elementEnd('form');
    }

    /**
     * Actually block a user.
     *
     * @return void
     */
    function blockProfile()
    {
        $cur = common_current_user();

        if ($cur->hasBlocked($this->profile)) {
            $this->clientError(_('You have already blocked this user.'));
            return;
        }
        $result = $cur->block($this->profile);
        if (!$result) {
            $this->serverError(_('Failed to save block information.'));
            return;
        }

        // Now, gotta figure where we go back to
        foreach ($this->args as $k => $v) {
            if ($k == 'returnto-action') {
                $action = $v;
            } elseif (substr($k, 0, 9) == 'returnto-') {
                $args[substr($k, 9)] = $v;
            }
        }

        if ($action) {
            common_redirect(common_local_url($action, $args), 303);
        } else {
            common_redirect(common_local_url('subscribers',
                                             array('nickname' => $cur->nickname)),
                            303);
        }
    }
}

