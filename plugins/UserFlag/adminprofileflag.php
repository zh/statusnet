<?php
/**
 * Show latest and greatest profile flags
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
 * Show the latest and greatest profile flags
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class AdminprofileflagAction extends Action
{
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

        $this->showPage();
    }

    function title() {
        return _('Flagged profiles');
    }

    /**
     * save the profile flag
     *
     * @return void
     */

    function showContent()
    {
        $profile = $this->getProfiles();

        $pl = new FlaggedProfileList($profile, $this);

        $pl->show();
    }

    function getProfiles()
    {
        $ufp = new User_flag_profile();

        $ufp->selectAdd();
        $ufp->selectAdd('profile_id');
        $ufp->selectAdd('count(*) as flag_count');

        $ufp->whereAdd('cleared is NULL');

        $ufp->groupBy('profile_id');
        $ufp->orderBy('flag_count DESC');

        $profiles = array();

        if ($ufp->find()) {
            while ($ufp->fetch()) {
                $profile = Profile::staticGet('id', $ufp->profile_id);
                if (!empty($profile)) {
                    $profiles[] = $profile;
                }
            }
        }

        $ufp->free();

        return new ArrayWrapper($profiles);
    }
}

class FlaggedProfileList extends ProfileList {

    function newListItem($profile)
    {
        return new FlaggedProfileListItem($this->profile, $this->action);
    }
}

class FlaggedProfileListItem extends ProfileListItem
{
    var $user = null;
    var $r2args = null;

    function showActions()
    {
        $this->user = common_current_user();

        list($action, $this->r2args) = $this->out->returnToArgs();

        $this->r2args['action'] = $action;

        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->out->elementStart('li', 'entity_moderation');
            $this->out->element('p', null, _('Moderate'));
            $this->out->elementStart('ul');
            $this->showSandboxButton();
            $this->showSilenceButton();
            $this->showDeleteButton();
            $this->showClearButton();
            $this->out->elementEnd('ul');
            $this->out->elementEnd('li');
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showSandboxButton()
    {
        if ($this->user->hasRight(Right::SANDBOXUSER)) {
            $this->out->elementStart('li', 'entity_sandbox');
            if ($this->profile->isSandboxed()) {
                $usf = new UnSandboxForm($this->out, $this->profile, $this->r2args);
                $usf->show();
            } else {
                $sf = new SandboxForm($this->out, $this->profile, $this->r2args);
                $sf->show();
            }
            $this->out->elementEnd('li');
        }
    }

    function showSilenceButton()
    {
        if ($this->user->hasRight(Right::SILENCEUSER)) {
            $this->out->elementStart('li', 'entity_silence');
            if ($this->profile->isSilenced()) {
                $usf = new UnSilenceForm($this->out, $this->profile, $this->r2args);
                $usf->show();
            } else {
                $sf = new SilenceForm($this->out, $this->profile, $this->r2args);
                $sf->show();
            }
            $this->out->elementEnd('li');
        }
    }

    function showDeleteButton()
    {

        if ($this->user->hasRight(Right::DELETEUSER)) {
            $this->out->elementStart('li', 'entity_delete');
            $df = new DeleteUserForm($this->out, $this->profile, $this->r2args);
            $df->show();
            $this->out->elementEnd('li');
        }
    }

    function showClearButton()
    {
    }
}
