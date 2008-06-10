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
			$this->no_such_notice();
		}

		if (!$notice->getProfile()) {
			$this->no_such_notice();
		}

		# Looks like we're good; show the header

		common_show_header($profile->nickname."'s status on ".common_date_string($notice->created));

		common_element_start('ul', array('id' => 'notices'));
		$this->show_notice($notice);
		common_element_end('ul');

		common_show_footer();
	}

	function no_such_notice() {
		common_user_error('No such notice.');
	}
}
