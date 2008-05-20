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

class ShownoticeAction extends Action {

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

		common_show_header($profile->nickname." status on ".$notice->created);

		$this->show_notice($notice);

		common_show_footer();
	}

	function no_such_notice() {
		common_user_error('No such notice.');
	}

	function show_notice($notice) {
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_element_start('div', array('class' => 'notice greenBg'));
		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
		if ($avatar) {
			common_element('img', array('src' => $avatar->url,
										'class' => 'avatar profile',
										'width' => AVATAR_PROFILE_SIZE,
										'height' => AVATAR_PROFILE_SIZE,
										'alt' =>
										($profile->fullname) ? $profile->fullname :
										                       $profile->nickname));
		}
		common_element('a', array('href' => $profile->profileurl,
								  'class' => 'nickname',
								  'title' =>
								  ($profile->fullname) ? $profile->fullname :
								  $profile->nickname),
					   $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element('span', array('class' => 'content'),
					   $notice->content);
		common_element('span', array('class' => 'date'),
					   common_date_string($notice->created));
		common_element_end('div');
	}
}
