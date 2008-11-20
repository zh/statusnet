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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	 If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/personal.php');

class StreamAction extends PersonalAction {


	function public_views_menu() {

		$action = $this->trimmed('action');

		common_debug("action = $action");

		common_element_start('ul', array('id' => 'nav_views'));

		common_menu_item(common_local_url('public'), _('Public'),
			_('Public timeline'), $action == 'public');

		common_menu_item(common_local_url('tag'), _('Recent tags'),
			_('Recent tags'), $action == 'tag');

		common_menu_item(common_local_url('featured'), _('Featured'),
			_('Notices from featured Users'), $action == 'featured');

		common_menu_item(common_local_url('favorited'), _('Favorited'),
			_("Most favorited notices"), $action == 'favorited');

		common_element_end('ul');

	}

	function show_notice($notice) {
		global $config;
		$profile = $notice->getProfile();
		$user = common_current_user();

		# XXX: RDFa
		common_element_start('li', array('class' => 'notice_single',
										  'id' => 'notice-' . $notice->id));
		if ($user) {
			if ($user->hasFave($notice)) {
				common_disfavor_form($notice);
			} else {
				common_favor_form($notice);
			}
		}
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
		if ($notice->rendered) {
			common_raw($notice->rendered);
		} else {
			# XXX: may be some uncooked notices in the DB,
			# we cook them right now. This should probably disappear in future
			# versions (>> 0.4.x)
			common_raw(common_render_content($notice->content, $notice));
		}
		common_element_end('p');
		$noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
		# XXX: we need to figure this out better. Is this right?
		if (strcmp($notice->uri, $noticeurl) != 0 && preg_match('/^http/', $notice->uri)) {
			$noticeurl = $notice->uri;
		}
		common_element_start('p', 'time');
		common_element('a', array('class' => 'permalink',
								  'href' => $noticeurl,
								  'title' => common_exact_date($notice->created)),
					   common_date_string($notice->created));
		if ($notice->source) {
			common_text(_(' from '));
			$this->source_link($notice->source);
		}
		if ($notice->reply_to) {
			$replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
			common_text(' (');
			common_element('a', array('class' => 'inreplyto',
									  'href' => $replyurl),
						   _('in reply to...'));
			common_text(')');
		}
		common_element_start('a',
							 array('href' => common_local_url('newnotice',
															  array('replyto' => $profile->nickname)),
								   'onclick' => 'return doreply("'.$profile->nickname.'", '.$notice->id.');',
								   'title' => _('reply'),
								   'class' => 'replybutton'));
		common_raw('&rarr;');
		common_element_end('a');
		if ($user && $notice->profile_id == $user->id) {
			$deleteurl = common_local_url('deletenotice', array('notice' => $notice->id));
			common_element_start('a', array('class' => 'deletenotice',
											'href' => $deleteurl,
											'title' => _('delete')));
			common_raw('&times;');
			common_element_end('a');
		}
		common_element_end('p');
		common_element_end('li');
	}
}
