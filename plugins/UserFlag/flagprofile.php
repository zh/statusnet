<?php
/**
 * Add a flag to a profile
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Action to flag a profile.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class FlagprofileAction extends Action
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
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new ClientException(_('Action only accepts POST'));
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

        $this->flagProfile();
        $this->returnTo();
    }

    function title() {
        return _('Flag profile');
    }

    /**
     * save the profile flag
     *
     * @return void
     */

    function flagProfile()
    {
    }
}

