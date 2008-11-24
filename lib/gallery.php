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
		
		$tag = $this->arg('tag');
		
		common_show_header($profile->nickname . ": " . $this->gallery_type(),
						   NULL, $profile,
						   array($this, 'show_top'));

		$this->display_links($profile, $page, $display);
		$this->show_tags_dropdown($profile);
		
		$this->show_gallery($profile, $page, $display, $tag);
		common_show_footer();
	}

	function no_such_user() {
		$this->client_error(_('No such user.'));
	}

	function show_tags_dropdown($profile) {
		$tag = $this->trimmed('tag');
		$tags = $profile->getAllTags();
		$content = array();
		foreach ($tags as $t) {
			$content[$t] = $t;
		}
		if ($tags) {
			common_element('a', array('href' => common_local_url($this->trimmed('action'),
																 array('nickname' => $profile->nickname))),
						   _('All'));
			common_element_start('form', array('name' => 'bytag', 'id' => 'bytag'));
			common_dropdown('tag', _('Tag'), $content,
							_('Choose a tag to narrow list'), FALSE, $tag);
			common_submit('go', _('Go'));
			common_element_end('form');
		}
	}
	
	function show_top($profile) {
		common_element('div', 'instructions',
					   $this->get_instructions($profile));
	}

	function show_gallery($profile, $page, $display='list', $tag=NULL) {

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
		# FIXME: SQL injection on $tag
		
		$other->query('SELECT profile.* ' .
					  'FROM profile JOIN subscription ' .
					  'ON profile.id = subscription.' . $lst . ' ' .
					  (($tag) ? 'JOIN profile_tag ON (profile.id = profile_tag.tagged AND subscription.'.$usr.'= profile_tag.tagger) ' : '') .
					  'WHERE ' . $usr . ' = ' . $profile->id . ' ' .
					  'AND subscriber != subscribed ' .
					  (($tag) ? 'AND profile_tag.tag= "' . $tag . '" ': '') .
					  'ORDER BY subscription.created DESC, profile.id DESC ' .
					  $lim);
		
		if ($display == 'list') {
			$profile_list = new ProfileList($other, $profile, $this->trimmed('action'));
			$cnt = $profile_list->show_list();
		} else {
			$cnt = $this->icon_list($other);
		}

		# For building the pagination URLs
		
		$args = array('nickname' => $profile->nickname);
		
		if ($display != 'list') {
			$args['display'] = $display;
		}
		
		common_pagination($page > 1,
						  $cnt > $per_page,
						  $page,
						  $this->trimmed('action'),
						  $args);
	}

	function icon_list($other) {
		
		common_element_start('ul', $this->div_class());

		$cnt = 0;
		
		while ($other->fetch()) {

			$cnt++;
			
			if ($cnt > AVATARS_PER_PAGE) {
				break;
			}
			
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
		
		return $cnt;
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
	
	function display_links($profile, $page, $display) {
		
		common_element_start('p');
		
		switch ($display) {
		 case 'list':
			common_element('span', NULL, _('List'));
			common_text(' | ');
			common_element('a', array('href' => common_local_url($this->trimmed('action'),
																 array('display' => 'icons',
																	   'nickname' => $profile->nickname,
																	   'page' => 1 + floor((($page - 1) * PROFILES_PER_PAGE) / AVATARS_PER_PAGE)))),
						   _('Icons'));
			break;
		 default:
			common_element('a', array('href' => common_local_url($this->trimmed('action'),
																 array('nickname' => $profile->nickname,
																	   'page' => 1 + floor((($page - 1) * AVATARS_PER_PAGE) / PROFILES_PER_PAGE)))),
						   _('List'));
			common_text(' | ');
			common_element('span', NULL, _('Icons'));
			break;
		}
		
		common_element_end('p');
	}
}