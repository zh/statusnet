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

class AllAction extends StreamAction {

	function handle($args) {

		parent::handle($args);

		$nickname = common_canonical_nickname($this->arg('nickname'));
		$user = User::staticGet('nickname', $nickname);

		if (!$user) {
			$this->no_such_user();
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_t('User record exists without profile.'));
			return;
		}

		# Looks like we're good; show the header

		common_show_header($profile->nickname . _t(" and friends"));

		$cur = common_current_user();

		if ($cur && $profile->id == $cur->id) {
			common_notice_form();
		}

		$this->show_notices($profile);
		
		common_show_footer();
	}

	function show_notices($profile) {

		$notice = DB_DataObject::factory('notice');

		# XXX: chokety and bad

		$notice->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$profile->id.' and subscribed = notice.profile_id)', 'OR');
		$notice->whereAdd('profile_id = ' . $profile->id, 'OR');

		$notice->orderBy('created DESC');

		$page = $this->arg('page') || 1;

		$notice->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE);

		$notice->find();

		common_element_start('div', 'notices width100');
		common_element('h2', 'notices', _t('Notices'));

		while ($notice->fetch()) {
			$this->show_notice($notice);
		}

		# XXX: show a link for the next page
		common_element_end('div');
	}
}
