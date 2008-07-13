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

class PublicAction extends StreamAction {

	function handle($args) {
		parent::handle($args);

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		header('X-XRDS-Location: '. common_local_url('publicxrds'));

		common_show_header(_('Public timeline'),
						   array($this, 'show_header'), NULL,
						   array($this, 'show_top'));

		# XXX: Public sidebar here?

		$this->show_notices($page);

		common_show_footer();
	}

	function show_top() {
		if (common_logged_in()) {
			common_notice_form('public');
		}
	}

	function show_header() {
		common_element('link', array('rel' => 'alternate',
									 'href' => common_local_url('publicrss'),
									 'type' => 'application/rss+xml',
									 'title' => _('Public Stream Feed')));
		# for client side of OpenID authentication
		common_element('meta', array('http-equiv' => 'X-XRDS-Location',
									 'content' => common_local_url('publicxrds')));
	}

	function show_notices($page) {

		$notice = DB_DataObject::factory('notice');

		# FIXME: bad performance

		$notice->whereAdd('EXISTS (SELECT user.id from user where user.id = notice.profile_id)');

		$notice->orderBy('created DESC, notice.id DESC');

		# We fetch one extra, to see if we need an "older" link

		$notice->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

		$cnt = $notice->find();

		if ($cnt > 0) {
			common_element_start('ul', array('id' => 'notices'));
			for ($i = 0; $i < min($cnt, NOTICES_PER_PAGE); $i++) {
				if ($notice->fetch()) {
					$this->show_notice($notice);
				} else {
					// shouldn't happen!
					break;
				}
			}
			common_element_end('ul');
		}

		common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
						  $page, 'public');
	}
}

