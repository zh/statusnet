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

require_once(INSTALLDIR.'/actions/showstream.php');

class ShowfavoritesAction extends StreamAction
{

    function handle($args)
    {

        parent::handle($args);

        $nickname = common_canonical_nickname($this->arg('nickname'));
        $user = User::staticGet('nickname', $nickname);

        if (!$user) {
            $this->clientError(_('No such user.'));
            return;
        }

        $profile = $user->getProfile();

        if (!$profile) {
            $this->serverError(_('User has no profile.'));
            return;
        }

        # Looks like we're good; show the header

        common_show_header(sprintf(_("%s favorite notices"), $profile->nickname),
                           array($this, 'show_header'), $user,
                           array($this, 'show_top'));

        $this->show_notices($user);

        common_show_footer();
    }

    function show_header($user)
    {
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('favoritesrss', array('nickname' =>
                                                                                      $user->nickname)),
                                     'type' => 'application/rss+xml',
                                     'title' => sprintf(_('Feed for favorites of %s'), $user->nickname)));
    }

    function show_top($user)
    {
        $cur = common_current_user();

        if ($cur && $cur->id == $user->id) {
            common_notice_form('all');
        }

        $this->show_feeds_list(array(0=>array('href'=>common_local_url('favoritesrss', array('nickname' => $user->nickname)),
                                              'type' => 'rss',
                                              'version' => 'RSS 1.0',
                                              'item' => 'Favorites')));
        $this->views_menu();
    }

    function show_notices($user)
    {

        $page = $this->trimmed('page');
        if (!$page) {
            $page = 1;
        }

        $notice = $user->favoriteNotices(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        if (!$notice) {
            $this->serverError(_('Could not retrieve favorite notices.'));
            return;
        }

        $cnt = $this->show_notice_list($notice);

        common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'showfavorites', array('nickname' => $user->nickname));
    }
}
