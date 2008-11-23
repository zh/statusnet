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

class NewnoticeAction extends Action {

	function handle($args) {
		parent::handle($args);

		if (!common_logged_in()) {
			common_user_error(_('Not logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {

			# CSRF protection - token set in common_notice_form()
			$token = $this->trimmed('token');
			if (!$token || $token != common_session_token()) {
				$this->client_error(_('There was a problem with your session token. Try again, please.'));
				return;
			}

			$this->save_new_notice();
		} else {
			$this->show_form();
		}
	}

	function save_new_notice() {

		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$content = $this->trimmed('status_textarea');

		if (!$content) {
			$this->show_form(_('No content!'));
			return;
		} else {
			$content = common_shorten_links($content);

			if (mb_strlen($content) > 140) {
				common_debug("Content = '$content'", __FILE__);
				common_debug("mb_strlen(\$content) = " . mb_strlen($content), __FILE__);
				$this->show_form(_('That\'s too long. Max notice size is 140 chars.'));
				return;
			}
		}

		$inter = new CommandInterpreter();

		$cmd = $inter->handle_command($user, $content);

		if ($cmd) {
			$cmd->execute(new WebChannel());
			return;
		}

		$replyto = $this->trimmed('inreplyto');

		common_debug("Replyto = $replyto\n");

		$notice = Notice::saveNew($user->id, $content, 'web', 1, ($replyto == 'false') ? NULL : $replyto);

		if (is_string($notice)) {
			$this->show_form($notice);
			return;
		}

		common_broadcast_notice($notice);

		if ($this->boolean('ajax')) {
			common_start_html('text/xml;charset=utf-8');
			common_element_start('head');
			common_element('title', null, _('Notice posted'));
			common_element_end('head');
			common_element_start('body');
			$this->show_notice($notice);
			common_element_end('body');
			common_element_end('html');
		} else {
			$returnto = $this->trimmed('returnto');

			if ($returnto) {
				$url = common_local_url($returnto,
										array('nickname' => $user->nickname));
			} else {
				$url = common_local_url('shownotice',
										array('notice' => $notice->id));
			}
			common_redirect($url, 303);
		}
	}

	function ajax_error_msg($msg) {
		common_start_html('text/xml;charset=utf-8');
		common_element_start('head');
		common_element('title', null, _('Ajax Error'));
		common_element_end('head');
		common_element_start('body');
		common_element('p', array('class' => 'error'), $msg);
		common_element_end('body');
		common_element_end('html');
	}

	function show_top($content=NULL) {
		common_notice_form(NULL, $content);
	}

	function show_form($msg=NULL) {
		if ($msg && $this->boolean('ajax')) {
			$this->ajax_error_msg($msg);
			return;
		}
		$content = $this->trimmed('status_textarea');
		if (!$content) {
			$replyto = $this->trimmed('replyto');
			$profile = Profile::staticGet('nickname', $replyto);
			if ($profile) {
				$content = '@' . $profile->nickname . ' ';
			}
		}
		common_show_header(_('New notice'), NULL, $content,
						   array($this, 'show_top'));
		if ($msg) {
			common_element('p', 'error', $msg);
		}
		common_show_footer();
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

		$returnto = $this->trimmed('returnto');

		# If this is the personal stream, we don't want avatars
		if ($returnto != 'showstream') {

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
		}

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
			$ns = Notice_source::staticGet($source);
			if ($ns) {
				common_element('a', array('href' => $ns->url),
							   $ns->name);
			} else {
				common_element('span', 'noticesource', $source_name);
			}
			break;
		}
		return;
	}


}
