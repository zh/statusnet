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

class NewnoticeAction extends Action {

	function handle($args) {
		parent::handle($args);
		# XXX: Ajax!

		if (!common_logged_in()) {
			common_user_error(_('Not logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->save_new_notice();
		} else {
			$this->show_form();
		}
	}

	function save_new_notice() {

		# CSRF protection - token set in common_notice_form()
		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->client_error(_('There was a problem with your session token. Try again, please.'));
			return;
		}

		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$content = $this->trimmed('status_textarea');

		if (!$content) {
			$this->show_form(_('No content!'));
			return;
//		} else if (mb_strlen($content) > 140) {
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

	function show_top($content=NULL) {
		common_notice_form(NULL, $content);
	}

	function show_form($msg=NULL) {
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
}
