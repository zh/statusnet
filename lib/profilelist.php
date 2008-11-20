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

define('PROFILES_PER_PAGE', 10);

class ProfileList {

	var $profile = NULL;
	
	function __construct($profile) {
		$this->profile = $profile;
	}
	
	function show_list() {
		
		common_element_start('ul', array('id' => 'profiles'));

		$cnt = 0;
		
		while ($this->profile->fetch()) {
			$cnt++;
			if($cnt > PROFILES_PER_PAGE) {
				break;
			}
			$this->show();
		}
		
		common_element_end('ul');
	}
	
	function show() {

		$this->profile = $this->profile;
		
		common_element_start('li', array('class' => 'profile_single',
										 'id' => 'profile-' . $this->profile->id));
		$avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);
		common_element_start('a', array('href' => $this->profile->profileurl));
		common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
									'class' => 'avatar stream',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' =>
									($this->profile->fullname) ? $this->profile->fullname :
									$this->profile->nickname));
		common_element_end('a');
		common_element_start('p');
		common_element_start('a', array('href' => $this->profile->profileurl,
										'class' => 'nickname'));
		common_raw($this->highlight($this->profile->nickname));
		common_element_end('a');
		if ($this->profile->fullname) {
			common_text(' | ');
			common_element_start('span', 'fullname');
			common_raw($this->highlight($this->profile->fullname));
			common_element_end('span');
		}
		if ($this->profile->location) {
			common_text(' | ');
			common_element_start('span', 'location');
			common_raw($this->highlight($this->profile->location));
			common_element_end('span');
		}
		common_element_end('p');
		if ($this->profile->homepage) {
			common_element_start('p', 'website');
			common_element_start('a', array('href' => $this->profile->homepage));
			common_raw($this->highlight($this->profile->homepage));
			common_element_end('a');
			common_element_end('p');
		}
		if ($this->profile->bio) {
			common_element_start('p', 'bio');
			common_raw($this->highlight($this->profile->bio));
			common_element_end('p');
		}
		common_element_end('li');
	}

	function highlight($text) {
		return htmlspecialchars($text);
	}
}