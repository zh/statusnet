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

define(PROFILES_PER_PAGE, 10);

# XXX common parent for people and content search?

class PeoplesearchAction extends Action {
	
	function handle($args) {
		parent::handle($args);
		$this->show_form();
	}

	function show_top($error=NULL) {
	}
	
	function show_form($error=NULL) {
		$q = $this->trimmed('q');
		$page = $this->trimmed('page', 1);
		
		common_show_header(_t('Find people'), NULL, $error, array($this, 'show_top'));
		common_element_start('form', array('method' => 'post',
										   'id' => 'login',
										   'action' => common_local_url('peoplesearch')));
		common_element_start('p');
		common_element('input', array('name' => 'q',
									  'id' => 'q',
									  'type' => 'text',
									  'class' => 'input_text',
									  'value' => ($q) ? $q : ''));
		common_text(' ');
		common_element('input', array('type' => 'submit',
									  'id' => 'search',
									  'name' => 'search',
									  'class' => 'submit',
									  'value' => _t('Search')));
					   
		common_element_end('p');
		if ($q) {
			common_element('hr');
			$this->show_results($q, $page);
		}
		common_element_end('form');
		common_show_footer();
	}
	
	function show_results($q, $page) {
		
		$profile = new Profile();

		# lcase it for comparison
		$q = strtolower($q);
		$profile->whereAdd('MATCH(nickname, fullname, location, bio, homepage) ' . 
						   'against (\''.addslashes($q).'\')');

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
		}
		
		common_pagination($page > 1, $cnt > PROFILES_PER_PAGE,
						  $page, 'peoplesearch', array('q' => $q));
	}
	
	function show_profile($profile, $terms) {
		common_start_element('li', array('class' => 'profile_single',
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
		common_element_start('a', array('href' => $profile->profileurl,
										'class' => 'nickname'));
		common_raw($this->highlight($profile->nickname, $terms));
		common_element_end('a');
		if ($profile->fullname) {
			common_element_start('p', 'fullname');
			common_raw($this->highlight($profile->fullname, $terms));
			common_element_end('p');
		}
		if ($profile->location) {
			common_element_start('p', 'location');
			common_raw($this->highlight($profile->location, $terms));
			common_element_end('p');
		}
		if ($profile->location) {
			common_element_start('p', 'bio');
			common_raw($this->highlight($profile->bio, $terms));
			common_element_end('p');
		}
		common_element_end('li');
	}

	function highlight($text, $terms) {
		$pattern = '/('.implode('|',array_map('htmlspecialchars', $terms)).')/';
		$result = preg_replace($pattern, '<strong>\\1</strong>', $text);
		return $result;
	}
}
