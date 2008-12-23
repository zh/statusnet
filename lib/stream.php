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

require_once(INSTALLDIR.'/lib/personal.php');
require_once(INSTALLDIR.'/lib/noticelist.php');

class StreamAction extends PersonalAction {

    function public_views_menu()
    {

        $action = $this->trimmed('action');

        common_element_start('ul', array('id' => 'nav_views'));

        common_menu_item(common_local_url('public'), _('Public'),
            _('Public timeline'), $action == 'public');

        common_menu_item(common_local_url('tag'), _('Recent tags'),
            _('Recent tags'), $action == 'tag');

        if (count(common_config('nickname', 'featured')) > 0) {
            common_menu_item(common_local_url('featured'), _('Featured'),
                _('Featured users'), $action == 'featured');
        }

        common_menu_item(common_local_url('favorited'), _('Popular'),
            _("Popular notices"), $action == 'favorited');

        common_element_end('ul');

    }

    function show_notice_list($notice)
    {
        $nl = new NoticeList($notice);
        return $nl->show();
    }
}
