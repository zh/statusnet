<?php
/*
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class UserrssAction extends Rss10Action
{

    var $user = null;

    function prepare($args)
    {
        parent::prepare($args);
        $nickname = $this->trimmed('nickname');
        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'));
            return false;
        } else {
            return true;
        }
    }

    function getNotices($limit=0)
    {

        $user = $this->user;

        if (is_null($user)) {
            return null;
        }

        $notice = $user->getNotices(0, ($limit == 0) ? NOTICES_PER_PAGE : $limit);

        $notices = array();
        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    function getChannel()
    {
        $user = $this->user;
        $profile = $user->getProfile();
        $c = array('url' => common_local_url('userrss',
                                             array('nickname' =>
                                                   $user->nickname)),
                   'title' => $user->nickname,
                   'link' => $profile->profileurl,
                   'description' => sprintf(_('Microblog by %s'), $user->nickname));
        return $c;
    }

    function getImage()
    {
        $user = $this->user;
        $profile = $user->getProfile();
        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            $this->serverError(_('User without matching profile'));
            return null;
        }
        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        return ($avatar) ? $avatar->url : null;
    }

    # override parent to add X-SUP-ID URL

    function initRss($limit=0)
    {
        $url = common_local_url('sup', null, null, $this->user->id);
        header('X-SUP-ID: '.$url);
        parent::initRss($limit);
    }

    function isReadOnly($args)
    {
        return true;
    }
}
