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

class StreamAction extends Action {

	function is_readonly() {
		return true;
	}

	function handle($args) {
		parent::handle($args);
                common_set_returnto($this->self_url());
	}

	function views_menu() {

		$user = NULL;
		$action = $this->trimmed('action');
		$nickname = $this->trimmed('nickname');

		if ($nickname) {
			$user = User::staticGet('nickname', $nickname);
			$user_profile = $user->getProfile();
		} else {
			$user_profile = false;
		}

		common_element_start('ul', array('id' => 'nav_views'));

		common_menu_item(common_local_url('all', array('nickname' =>
													   $nickname)),
						 _('Personal'),
						 sprintf(_('%s and friends'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
						 $action == 'all');
		common_menu_item(common_local_url('replies', array('nickname' =>
															  $nickname)),
						 _('Replies'),
						 sprintf(_('Replies to %s'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
						 $action == 'replies');
		common_menu_item(common_local_url('showstream', array('nickname' =>
															  $nickname)),
						 _('Profile'),
						 ($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname,
						 $action == 'showstream');
		common_element_end('ul');
	}

	function show_notice($notice) {
		global $config;
		$profile = $notice->getProfile();
		if (common_logged_in()) {
			$user = common_current_user();
			$user_profile = $user->getProfile();
		} else {
			$user_profile = false;
		}

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
								   'onclick' => 'doreply("'.$profile->nickname.'"); return false',
								   'title' => _('reply'),
								   'class' => 'replybutton'));
		common_raw('&rarr;');
		common_element_end('a');
		common_element_end('p');
		if ($user_profile && $notice->profile_id == $user_profile->id) {
			$deleteurl = common_local_url('deletenotice', array('notice' => $notice->id));
			common_element('a', array('class' => 'deletenotice',
									 'href' => $deleteurl),
						   _('delete'));
		}
		common_element_end('li');
	}
	
	function source_link($source) {
		$source_name = _($source);
		switch ($source) {
		 case 'web':
		 case 'xmpp':
		 case 'mail':
		 case 'omb':
		 case 'api':
			common_element('span', 'noticesource', $source_name);
			break;
		 default:
			$ns = new Notice_source($source);
			if ($ns) {
				common_element('a', array('href' => $ns->url),
							   $ns->name);
			}
		}
		return;
	}
}
