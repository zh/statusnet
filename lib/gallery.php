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

# 9x9

define('AVATARS_PER_PAGE', 81);

class GalleryAction extends Action {

	function handle($args) {
		parent::handle($args);
		$nickname = $this->arg('nickname');
		$profile = Profile::staticGet('nickname', $nickname);
		if (!$profile) {
			$this->no_such_user();
			return;
		}
		$user = User::staticGet($profile->id);
		if (!$user) {
			$this->no_such_user();
			return;
		}
		$page = $this->arg('page');
		if (!$page) {
			$page = 1;
		}
		common_show_header($profile->nickname . ": " . $this->gallery_type(),
						   NULL, $profile,
						   array($this, 'show_top'));
		$this->show_gallery($profile, $page);
		common_show_footer();
	}

	function no_such_user() {
		$this->client_error(_t('No such user.'));
	}
	
	function show_top($profile) {
		common_element('p', 'instructions',
					   $this->get_instructions($profile));
	}
	
	function show_gallery($profile, $page) {

		$subs = new Subscription();
		
		$this->define_subs($subs, $profile);
		
		$subs->orderBy('created DESC');

		# We ask for an extra one to know if we need to do another page

		$subs->limit((($page-1)*AVATARS_PER_PAGE), AVATARS_PER_PAGE + 1);

		$subs_count = $subs->find();

		if ($subs_count == 0) {
			common_element('p', _t('Nobody to show!'));
			return;
		}
		
		common_element_start('ul', $this->div_class());

		for ($idx = 0; $idx < min($subs_count, AVATARS_PER_PAGE); $idx++) {
			
			$result = $subs->fetch();
			
			if (!$result) {
				common_debug('Ran out of subscribers too early.', __FILE__);
				break;
			}

			$other = Profile::staticGet($this->get_other($subs));

			common_element_start('li');
			
			common_element_start('a', array('title' => ($other->fullname) ?
											$other->fullname :
											$other->nickname,
											'href' => $other->profileurl,
											'class' => 'subscription'));
			$avatar = $other->getAvatar(AVATAR_STREAM_SIZE);
			common_element('img', 
						   array('src' => 
								 (($avatar) ? $avatar->url : 
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

		common_pagination($page > 1, 
						  $subs_count > AVATARS_PER_PAGE,
						  $page, 
						  $this->trimmed('action'), 
						  array('nickname' => $profile->nickname));
	}
	
	function gallery_type() {
		return NULL;
	}

	function get_instructions(&$profile) {
		return NULL;
	}

	function define_subs(&$subs, &$profile) {
		return;
	}

	function get_other(&$subs) {
		return NULL;
	}
	
	function div_class() {
		return '';
	}
}