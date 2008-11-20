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

require_once(INSTALLDIR.'/lib/profilelist.php');

# 10x8

define('AVATARS_PER_PAGE', 80);

class GalleryAction extends Action {

	function is_readonly() {
		return true;
	}

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
			$this->server_error(_('User without matching profile in system.'));
			return;
		}

		$page = $this->arg('page');
		
		if (!$page) {
			$page = 1;
		}

		$display = $this->arg('display');
		
		if (!$display) {
			$display = 'list';
		}
		
		common_show_header($profile->nickname . ": " . $this->gallery_type(),
						   NULL, $profile,
						   array($this, 'show_top'));
		$this->show_gallery($profile, $page, $display);
		common_show_footer();
	}

	function no_such_user() {
		$this->client_error(_('No such user.'));
	}

	function show_top($profile) {
		common_element('div', 'instructions',
					   $this->get_instructions($profile));
	}

	function show_gallery($profile, $page, $display='list') {

		$other = new Profile();
		
		list($lst, $usr) = $this->fields();

		$per_page = ($display == 'list') ? PROFILES_PER_PAGE : AVATARS_PER_PAGE;

		$offset = ($page-1)*$per_page;
		$limit = $per_page + 1;
		
		if (common_config('db','type') == 'pgsql') {
			$lim = ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		} else {
			$lim = ' LIMIT ' . $offset . ', ' . $limit;
		}

		# XXX: memcached results
		
		$cnt = $other->query('SELECT profile.* ' .
							 'FROM profile JOIN subscription ' .
							 'ON profile.id = subscription.' . $lst . ' ' .
							 'WHERE ' . $usr . ' = ' . $profile->id . ' ' .
							 'AND ' . $lst . ' != ' . $usr . ' ' .
							 'ORDER BY subscription.created DESC ' . 
							 $lim);
		
		if ($cnt == 0) {
			common_element('p', _('Nobody to show!'));
			return;
		}

		if ($display == 'list') {
			$profile_list = new ProfileList($other);
			$profile_list->show_list();
		} else {
			$this->icon_list($profile, $cnt);
		}
		
		common_pagination($page > 1,
						  $subs_count > AVATARS_PER_PAGE,
						  $page,
						  $this->trimmed('action'),
						  array('nickname' => $profile->nickname));
	}

	function icon_list($other, $subs_count) {
		
		common_element_start('ul', $this->div_class());
		
		for ($idx = 0; $idx < min($subs_count, AVATARS_PER_PAGE); $idx++) {

			$other->fetch();

			common_element_start('li');

			common_element_start('a', array('title' => ($other->fullname) ?
											$other->fullname :
											$other->nickname,
											'href' => $other->profileurl,
											'class' => 'subscription'));
			$avatar = $other->getAvatar(AVATAR_STREAM_SIZE);
			common_element('img',
						   array('src' =>
								 (($avatar) ? common_avatar_display_url($avatar) :
								  common_default_avatar(AVATAR_STREAM_SIZE)),
								 'width' => AVATAR_STREAM_SIZE,
								 'height' => AVATAR_STREAM_SIZE,
								 'class' => 'avatar stream',
								 'alt' => ($other->fullname) ?
								 $other->fullname :
								 $other->nickname));
			common_element_end('a');

			# XXX: subscribe form here

			common_element_end('li');
		}
			
		common_element_end('ul');
	}
	
	function gallery_type() {
		return NULL;
	}

	function get_instructions(&$profile) {
		return NULL;
	}

	function fields() {
		return NULL;
	}

	function div_class() {
		return '';
	}
}