<?php
/**
 * Superclass for actions that operate on a user
 *
 * PHP version 5
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for actions that operate on a user
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class ProfileFormAction extends Action
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

        $this->checkSessionToken();

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return false;
        }

        $id = $this->trimmed('profileid');

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
            $this->handlePost();
            $this->returnToArgs();
        }
    }

    /**
     * Return to the calling page based on hidden arguments
     *
     * @return void
     */

    function returnToArgs()
    {
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
            $this->clientError(_("No return-to arguments"));
        }
    }

    /**
     * handle a POST request
     *
     * sub-classes should overload this request
     *
     * @return void
     */

    function handlePost()
    {
        return;
    }
}
