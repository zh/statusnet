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
  
if (!defined('LACONICA')) { exit(1) }

define('NOTICES_PER_PAGE', 20);

class StreamAction extends Action {

	function handle($args) {
		parent::handle($args);
	}

	# XXX: for 'showstream' repeats same avatar over and over
	function show_notice($notice) {
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_start_element('div', array('class' => 'notice'));
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		common_start_element('a', array('href' => $profile->profileurl));
		common_element('img', array('src' => ($avatar) ? $avatar->url : DEFAULT_STREAM_AVATAR,
									'class' => 'avatar stream',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' => 
									($profile->fullname) ? $profile->fullname : 
									$profile->nickname));
		common_end_element('a');
		common_element('a', array('href' => $profile->profileurl,
								  'class' => 'nickname'),
					   $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element('span', array('class' => 'content'), $notice->content);
		$noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
		common_element('a', array('class' => 'notice',
								  'href' => $noticeurl),
					   common_date_string($notice->created));
		common_end_element('div');
	}
}
