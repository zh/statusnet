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

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class AllrssAction extends Rss10Action {

	var $user = NULL;

	function init() {
		$nickname = $this->trimmed('nickname');
		$this->user = User::staticGet('nickname', $nickname);
		
		if (!$this->user) {
			common_user_error(_('No such nickname.'));
			return false;
		} else {
			return true;
		}
	}
	
	function get_notices($limit=0) {
		
		$user = $this->user;
		$notices = array();
		
		$notice = DB_DataObject::factory('notice');

		$notice->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$user->id.' and subscribed = notice.profile_id)', 'OR');
		$notice->whereAdd('profile_id = ' . $user->id, 'OR');

		$notice->orderBy('created DESC');
		if ($limit != 0) {
			$notice->limit(0, $limit);
		}
		$notice->find();
		
		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}
		
		return $notices;
	}
	
	function get_channel() {
		$user = $this->user;
		$c = array('url' => common_local_url('allrss',
											 array('nickname' =>
												   $user->nickname)),
				   'title' => sprintf(_('%s and friends'), $user->nickname),
				   'link' => common_local_url('all',
											 array('nickname' =>
												   $user->nickname)),
				   'description' => sprintf(_('Feed for friends of %s'), $user->nickname));
		return $c;
	}
	
	function get_image() {
		$user = $this->user;
		$profile = $user->getProfile();
		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
		return ($avatar) ? $avatar->url : NULL;
	}
}