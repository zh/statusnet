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
    var $flag    = null;

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

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new ClientException(_('Action only accepts POST'));
        }

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return false;
        }

        $id = $this->trimmed('flagprofileto');

        if (!$id) {
            $this->clientError(_('No profile specified.'));
            return false;
        }

        $this->profile = Profile::staticGet('id', $id);

        if (empty($this->profile)) {
            $this->clientError(_('No profile with that ID.'));
            return false;
        }

        $user = common_current_user();

        assert(!empty($user)); // checked above

        if (User_flag_profile::exists($this->profile->id,
                                      $user->id))
        {
            $this->clientError(_('Flag already exists.'));
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

        $this->flagProfile();
        
        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            $this->element('title', null, _('Flagged for review'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', 'flagged', _('Flagged'));
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->returnTo();
        }
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
        $user = common_current_user();

        assert(!empty($user));
        assert(!empty($this->profile));

        $ufp = new User_flag_profile();

        $ufp->profile_id = $this->profile->id;
        $ufp->user_id    = $user->id;
        $ufp->created    = common_sql_now();

        if (!$ufp->insert()) {
            throw new ServerException(sprintf(_("Couldn't flag profile '%s' with flag '%s'."),
                                              $this->profile->nickname, $this->flag));
        }

        $ufp->free();
    }

    function returnTo()
    {
        // Now, gotta figure where we go back to
        foreach ($this->args as $k => $v) {
            if ($k == 'returnto-action') {
                $action = $v;
            } elseif (substr($k, 0, 9) == 'returnto-') {
                $args[substr($k, 9)] = $v;
            }
        }

        common_redirect(common_local_url($action, $args), 303);
    }
}

