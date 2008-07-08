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

require_once(INSTALLDIR.'/lib/stream.php');

class ShownoticeAction extends StreamAction {

	function handle($args) {
		parent::handle($args);
		$id = $this->arg('notice');
		$notice = Notice::staticGet($id);

		if (!$notice) {
			$this->client_error(_t('No such notice.'), 404);
			return;
		}

		$profile = $notice->getProfile();

		if (!$profile) {
			$this->server_error(_t('Notice has no profile'), 500);
			return;
		}

		# Looks like we're good; show the header

		common_show_header(sprintf(_('%1$s\'s status on %2$s'), $profile->nickname, common_exact_date($notice->created)),
						   NULL, $profile,
						   array($this, 'show_top'));

		common_element_start('ul', array('id' => 'notices'));
		$this->show_notice($notice);
		common_element_end('ul');

		common_show_footer();
	}

	function show_top($user) {
		$cur = common_current_user();

		if ($cur && $cur->id == $user->id) {
			common_notice_form();
		}
	}

	function no_such_notice() {
		common_user_error('No such notice.');
	}
}
