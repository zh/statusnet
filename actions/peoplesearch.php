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

require_once(INSTALLDIR.'/lib/searchaction.php');
define('PROFILES_PER_PAGE', 10);

class PeoplesearchAction extends SearchAction {

	function get_instructions() {
		return _('Search for people on %%site.name%% by their name, location, or interests. ' .
				  'Separate the terms by spaces; they must be 3 characters or more.');
	}

	function get_title() {
		return _('People search');
	}

	function show_results($q, $page) {

		$profile = new Profile();

		# lcase it for comparison
		$q = strtolower($q);

		if(common_config('db','type')=='mysql') {
			$profile->whereAdd('MATCH(nickname, fullname, location, bio, homepage) ' .
						   'against (\''.addslashes($q).'\')');
		} else {
			$profile->whereAdd('textsearch @@ plainto_tsquery(\''.addslashes($q).'\')');
		}

		# Ask for an extra to see if there's more.

		$profile->limit((($page-1)*PROFILES_PER_PAGE), PROFILES_PER_PAGE + 1);

		$cnt = $profile->find();

		if ($cnt > 0) {
			$terms = preg_split('/[\s,]+/', $q);
			common_element_start('ul', array('id' => 'profiles'));
			for ($i = 0; $i < min($cnt, PROFILES_PER_PAGE); $i++) {
				if ($profile->fetch()) {
					$this->show_profile($profile, $terms);
				} else {
					// shouldn't happen!
					break;
				}
			}
			common_element_end('ul');
		} else {
			common_element('p', 'error', _('No results'));
		}

		common_pagination($page > 1, $cnt > PROFILES_PER_PAGE,
						  $page, 'peoplesearch', array('q' => $q));
	}

	function show_profile($profile, $terms) {
		common_element_start('li', array('class' => 'profile_single',
										 'id' => 'profile-' . $profile->id));
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		common_element_start('a', array('href' => $profile->profileurl));
		common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
									'class' => 'avatar stream',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' =>
									($profile->fullname) ? $profile->fullname :
									$profile->nickname));
		common_element_end('a');
		common_element_start('p');
		common_element_start('a', array('href' => $profile->profileurl,
										'class' => 'nickname'));
		common_raw($this->highlight($profile->nickname, $terms));
		common_element_end('a');
		if ($profile->fullname) {
			common_text(' | ');
			common_element_start('span', 'fullname');
			common_raw($this->highlight($profile->fullname, $terms));
			common_element_end('span');
		}
		if ($profile->location) {
			common_text(' | ');
			common_element_start('span', 'location');
			common_raw($this->highlight($profile->location, $terms));
			common_element_end('span');
		}
		common_element_end('p');
		if ($profile->homepage) {
			common_element_start('p', 'website');
			common_element_start('a', array('href' => $profile->homepage));
			common_raw($this->highlight($profile->homepage, $terms));
			common_element_end('a');
			common_element_end('p');
		}
		if ($profile->bio) {
			common_element_start('p', 'bio');
			common_raw($this->highlight($profile->bio, $terms));
			common_element_end('p');
		}
		common_element_end('li');
	}

	function highlight($text, $terms) {
		$terms = array_map('preg_quote', array_map('htmlspecialchars', $terms));
		$pattern = '/('.implode('|',$terms).')/i';
		$result = preg_replace($pattern, '<strong>\\1</strong>', htmlspecialchars($text));
		return $result;
	}
}
