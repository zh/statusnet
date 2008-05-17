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

class PublicAction extends StreamAction {

	function handle($args) {
		parent::handle($args);

		$page = $this->arg('page') || 1;

		common_show_header(_t('Public timeline'));

		# XXX: Public sidebar here?

		$this->show_notices($page);

		common_show_footer();
	}

	function show_notices($page) {

		$notice = DB_DataObject::factory('notice');

		# XXX: filter out private notifications

		$notice->orderBy('created DESC');
		$notice->limit((($page-1)*NOTICES_PER_PAGE) + 1, NOTICES_PER_PAGE);

		$notice->find();

		common_element_start('div', 'notices');

		while ($notice->fetch()) {
			$this->show_notice($notice);
		}

		common_element_end('div');
	}
}

