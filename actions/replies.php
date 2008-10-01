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

class RepliesAction extends StreamAction {

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
			common_server_error(_('User has no profile.'));
			return;
		}

		# Looks like we're good; show the header

		common_show_header(sprintf(_("Replies to %s"), $profile->nickname),
						   array($this, 'show_header'), $user,
						   array($this, 'show_top'));

		$this->show_replies($user);

		common_show_footer();
	}

	function no_such_user() {
		common_user_error(_('No such user.'));
	}

	function show_header($user) {
		common_element('link', array('rel' => 'alternate',
									 'href' => common_local_url('repliesrss', array('nickname' =>
																					$user->nickname)),
									 'type' => 'application/rss+xml',
									 'title' => sprintf(_('Feed for replies to %s'), $user->nickname)));
	}

	function show_top($user) {
		$cur = common_current_user();

		if ($cur && $cur->id == $user->id) {
			common_notice_form('replies');
		}

		$this->views_menu();
	}

	function show_replies($user) {

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		$notice = $user->getReplies(($page-1) * NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
		
		$cnt = 0;
		
		if ($notice) {
			common_element_start('ul', array('id' => 'notices'));
			while ($notice->fetch()) {
				$cnt++;
				if ($cnt > NOTICES_PER_PAGE) {
					break;
				}
				$this->show_notice($notice);
			}
			common_element_end('ul');
		}

		common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
						  $page, 'replies', array('nickname' => $user->nickname));
	}
}
