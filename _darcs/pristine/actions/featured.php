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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/stream.php');
require_once(INSTALLDIR.'/lib/profilelist.php');

class FeaturedAction extends StreamAction {

    function handle($args)
    {
        parent::handle($args);

        $page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_show_header(_('Featured users'),
                           array($this, 'show_header'), null,
                           array($this, 'show_top'));

        $this->show_notices($page);

        common_show_footer();
    }

    function show_top()
    {
        $instr = $this->get_instructions();
        $output = common_markup_to_html($instr);
        common_element_start('div', 'instructions');
        common_raw($output);
        common_element_end('div');
        $this->public_views_menu();
    }

    function show_header()
    {
    }

    function get_instructions()
    {
        return _('Featured users');
    }

    function show_notices($page)
    {

        // XXX: Note I'm doing it this two-stage way because a raw query
        // with a JOIN was *not* working. --Zach

        $featured_nicks = common_config('nickname', 'featured');

        if (count($featured_nicks) > 0) {

            $quoted = array();

            foreach ($featured_nicks as $nick) {
                $quoted[] = "'$nick'";
            }

            $user = new User;
            $user->whereAdd(sprintf('nickname IN (%s)', implode(',', $quoted)));
            $user->limit(($page - 1) * PROFILES_PER_PAGE, PROFILES_PER_PAGE + 1);
            $user->orderBy('user.nickname ASC');

            $user->find();

            $profile_ids = array();

            while ($user->fetch()) {
                $profile_ids[] = $user->id;
            }

            $profile = new Profile;
            $profile->whereAdd(sprintf('profile.id IN (%s)', implode(',', $profile_ids)));
            $profile->orderBy('nickname ASC');

            $cnt = $profile->find();

            if ($cnt > 0) {
                $featured = new ProfileList($profile);
                $featured->show_list();
            }

            $profile->free();

            common_pagination($page > 1, $cnt > PROFILES_PER_PAGE, $page, 'featured');
        }
    }

}