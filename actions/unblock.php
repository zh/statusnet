<?php
/**
 * Unblock a user action class.
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
 * Unblock a user action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class UnblockAction extends Action
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
        $id = $this->trimmed('unblockto');
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
            $this->unblockProfile();
        }
    }

    /**
     * Unblock a user.
     *
     * @return void
     */
    function unblockProfile()
    {
        $cur    = common_current_user();
        $result = $cur->unblock($this->profile);
        if (!$result) {
            $this->serverError(_('Error removing the block.'));
            return;
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
            common_redirect(common_local_url('subscribers',
                                             array('nickname' => $cur->nickname)),
                            303);
        }
    }
}

