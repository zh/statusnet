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

define('NOTICES_PER_PAGE', 20);

class StreamAction extends Action {

	function handle($args) {
		parent::handle($args);
	}

	function views_menu() {

		$user = NULL;
		$action = $this->trimmed('action');
		$nickname = $this->trimmed('nickname');

		if ($nickname) {
			$user = User::staticGet('nickname', $nickname);
		}

		common_element_start('ul', array('id' => 'nav_views'));

		common_menu_item(common_local_url('all', array('nickname' =>
													   $nickname)),
						 _t('Personal'),
						 (($user && $user->fullname) ? $user->fullname : $nickname) . _t(' and friends'),
						 $action == 'all');
		common_menu_item(common_local_url('replies', array('nickname' =>
															  $nickname)),
						 _t('Replies'),  
						 _t('Replies to ') . (($user && $user->fullname) ? $user->fullname : $nickname),
						 $action == 'replies');
		common_menu_item(common_local_url('showstream', array('nickname' =>
															  $nickname)),
						 _t('Profile'),
						 ($user && $user->fullname) ? $user->fullname : $nickname,
						 $action == 'showstream');
		common_element_end('ul');
	}

	function show_notice($notice) {
		global $config;
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_element_start('li', array('class' => 'notice_single',
										  'id' => 'notice-' . $notice->id));
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
		common_element('a', array('href' => $profile->profileurl,
								  'class' => 'nickname'),
					   $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element_start('p', array('class' => 'content'));
		common_raw(common_render_content($notice->content, $notice));
		common_element_end('p');
		$noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
		common_element_start('p', 'time');
		common_element('a', array('class' => 'notice',
								  'href' => $noticeurl,
								  'title' => common_exact_date($notice->created)),
					   common_date_string($notice->created));
		common_element_end('p');
		common_element_end('li');
	}

	# XXX: these are almost identical functions!
	
	function show_reply($notice, $replied_id) {
		global $config;
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_element_start('li', array('class' => 'notice_single',
										  'id' => 'notice-' . $notice->id));
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		common_element_start('a', array('href' => $profile->profileurl));
		common_element('img', array('src' => ($avatar) ? $avatar->url : common_default_avatar(AVATAR_STREAM_SIZE),
									'class' => 'avatar stream',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' =>
									($profile->fullname) ? $profile->fullname :
									$profile->nickname));
		common_element_end('a');
		common_element('a', array('href' => $profile->profileurl,
								  'class' => 'nickname'),
					   $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element_start('p', array('class' => 'content'));
		common_raw(common_render_content($notice->content, $notice));
		common_element_end('p');
		$replyurl = common_local_url('shownotice', array('notice' => $replied_id));
		$noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
		common_element_start('p', 'time');
		common_element('a', array('class' => 'notice',
								  'href' => $noticeurl),
					   common_date_string($notice->created));
		common_element('a', array('class' => 'inreplyto',
								  'href' => $replyurl),
					   " in reply to ".$profile->nickname );
		common_element_end('p');
		common_element_end('li');
	}
}
