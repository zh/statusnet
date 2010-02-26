<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Edit an existing group
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @author   Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Add a new group
 *
 * This is the form for adding a new group
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class EditgroupAction extends GroupDesignAction
{

    var $msg;

    function title()
    {
        return sprintf(_('Edit %s group'), $this->group->nickname);
    }

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to create a group.'));
            return false;
        }

        $nickname_arg = $this->trimmed('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            common_redirect(common_local_url('editgroup', $args), 301);
            return false;
        }

        if (!$nickname) {
            $this->clientError(_('No nickname.'), 404);
            return false;
        }

        $groupid = $this->trimmed('groupid');

        if ($groupid) {
            $this->group = User_group::staticGet('id', $groupid);
        } else {
            $local = Local_group::staticGet('nickname', $nickname);
            if ($local) {
                $this->group = User_group::staticGet('id', $local->group_id);
            }
        }

        if (!$this->group) {
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $cur = common_current_user();

        if (!$cur->isAdmin($this->group)) {
            $this->clientError(_('You must be an admin to edit the group.'), 403);
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the group.
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->trySave();
        } else {
            $this->showForm();
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new GroupNav($this, $this->group);
        $nav->show();
    }

    function showContent()
    {
        $form = new GroupEditForm($this, $this->group);
        $form->show();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           _('Use this form to edit the group.'));
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('nickname');
    }

    function trySave()
    {
        $cur = common_current_user();
        if (!$cur->isAdmin($this->group)) {
            $this->clientError(_('You must be an admin to edit the group.'), 403);
            return;
        }

        $nickname    = common_canonical_nickname($this->trimmed('nickname'));
        $fullname    = $this->trimmed('fullname');
        $homepage    = $this->trimmed('homepage');
        $description = $this->trimmed('description');
        $location    = $this->trimmed('location');
        $aliasstring = $this->trimmed('aliases');

        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => NICKNAME_FMT))) {
            $this->showForm(_('Nickname must have only lowercase letters '.
                              'and numbers and no spaces.'));
            return;
        } else if ($this->nicknameExists($nickname)) {
            $this->showForm(_('Nickname already in use. Try another one.'));
            return;
        } else if (!User_group::allowedNickname($nickname)) {
            $this->showForm(_('Not a valid nickname.'));
            return;
        } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                   !Validate::uri($homepage,
                                  array('allowed_schemes' =>
                                        array('http', 'https')))) {
            $this->showForm(_('Homepage is not a valid URL.'));
            return;
        } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
            $this->showForm(_('Full name is too long (max 255 chars).'));
            return;
        } else if (User_group::descriptionTooLong($description)) {
            $this->showForm(sprintf(_('description is too long (max %d chars).'), User_group::maxDescription()));
            return;
        } else if (!is_null($location) && mb_strlen($location) > 255) {
            $this->showForm(_('Location is too long (max 255 chars).'));
            return;
        }

        if (!empty($aliasstring)) {
            $aliases = array_map('common_canonical_nickname', array_unique(preg_split('/[\s,]+/', $aliasstring)));
        } else {
            $aliases = array();
        }

        if (count($aliases) > common_config('group', 'maxaliases')) {
            $this->showForm(sprintf(_('Too many aliases! Maximum %d.'),
                                    common_config('group', 'maxaliases')));
            return;
        }

        foreach ($aliases as $alias) {
            if (!Validate::string($alias, array('min_length' => 1,
                                                'max_length' => 64,
                                                'format' => NICKNAME_FMT))) {
                $this->showForm(sprintf(_('Invalid alias: "%s"'), $alias));
                return;
            }
            if ($this->nicknameExists($alias)) {
                $this->showForm(sprintf(_('Alias "%s" already in use. Try another one.'),
                                        $alias));
                return;
            }
            // XXX assumes alphanum nicknames
            if (strcmp($alias, $nickname) == 0) {
                $this->showForm(_('Alias can\'t be the same as nickname.'));
                return;
            }
        }

        $this->group->query('BEGIN');

        $orig = clone($this->group);

        $this->group->nickname    = $nickname;
        $this->group->fullname    = $fullname;
        $this->group->homepage    = $homepage;
        $this->group->description = $description;
        $this->group->location    = $location;
        $this->group->mainpage    = common_local_url('showgroup', array('nickname' => $nickname));

        $result = $this->group->update($orig);

        if (!$result) {
            common_log_db_error($this->group, 'UPDATE', __FILE__);
            $this->serverError(_('Could not update group.'));
        }

        $result = $this->group->setAliases($aliases);

        if (!$result) {
            $this->serverError(_('Could not create aliases.'));
        }

        if ($nickname != $orig->nickname) {
            common_log(LOG_INFO, "Saving local group info.");
            $local = Local_group::staticGet('group_id', $this->group->id);
            $local->setNickname($nickname);
        }

        $this->group->query('COMMIT');

        if ($this->group->nickname != $orig->nickname) {
            common_redirect(common_local_url('editgroup',
                                             array('nickname' => $nickname)),
                            303);
        } else {
            $this->showForm(_('Options saved.'));
        }
    }

    function nicknameExists($nickname)
    {
        $group = Local_group::staticGet('nickname', $nickname);

        if (!empty($group) &&
            $group->group_id != $this->group->id) {
            return true;
        }

        $alias = Group_alias::staticGet('alias', $nickname);

        if (!empty($alias) &&
            $alias->group_id != $this->group->id) {
            return true;
        }

        return false;
    }
}

