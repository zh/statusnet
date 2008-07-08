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
			$this->client_error(sprintf(_('No such user: %s'), $nickname));
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User record exists without profile.'));
			return;
		}

		# Looks like we're good; show the header

		common_show_header(sprintf(_("%s and friends"), $profile->nickname),
						   array($this, 'show_header'), $user,
						   array($this, 'show_top'));
		
		$this->show_notices($profile);
		
		common_show_footer();
	}
	
	function show_header($user) {
		common_element('link', array('rel' => 'alternate',
									 'href' => common_local_url('allrss', array('nickname' =>
																			   $user->nickname)),
									 'type' => 'application/rss+xml',
									 'title' => sprintf(_('Feed for friends of %s'), $user->nickname)));
	}

	function show_top($user) {
		$cur = common_current_user();
		
		if ($cur && $cur->id == $user->id) {
			common_notice_form('all');
		}
		
		$this->views_menu();
	}
	
	function show_notices($profile) {

		$notice = DB_DataObject::factory('notice');

		# XXX: chokety and bad

		$notice->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$profile->id.' and subscribed = notice.profile_id)', 'OR');
		$notice->whereAdd('profile_id = ' . $profile->id, 'OR');

		$notice->orderBy('created DESC');

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

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
						  $page, 'all', array('nickname' => $profile->nickname));
	}
}
