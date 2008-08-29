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

require_once(INSTALLDIR.'/lib/deleteaction.php');

class DeletenoticeAction extends DeleteAction {
	function handle($args) {
		parent::handle($args);
		# XXX: Ajax!

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->delete_notice();
		} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$this->show_form();
		}
	}

	function get_instructions() {
		return _('You are about to permanently delete a notice.  Once this is done, it cannot be undone.');
	}

	function get_title() {
		return _('Delete notice');
	}

	function show_form($error=NULL) {
		$user = common_current_user();

		common_show_header($this->get_title(), array($this, 'show_header'), NULL,
						   array($this, 'show_top'));
		common_element_start('form', array('id' => 'notice_delete_form',
								   'method' => 'post',
								   'action' => common_local_url('deletenotice')));
		common_hidden('token', common_session_token());
		common_hidden('notice', $this->trimmed('notice'));
		common_element_start('p');
		common_element('span', array('id' => 'confirmation_text'), _('Are you sure you want to delete this notice?'));

		common_element('input', array('id' => 'submit_no',
						  'name' => 'submit',
						  'type' => 'submit',
						  'value' => _('No')));
		common_element('input', array('id' => 'submit_yes',
						  'name' => 'submit',
						  'type' => 'submit',
						  'value' => _('Yes')));
		common_element_end('p');
		common_element_end('form');
		common_show_footer();
	}

	function delete_notice() {
		# CSRF protection
		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->show_form(_('There was a problem with your session token. Try again, please.'));
			return;
		}
		$url = common_get_returnto();
		$confirmed = $this->trimmed('submit');
		if ($confirmed == _('Yes')) {
			$user = common_current_user();
			$notice_id = $this->trimmed('notice');
			$notice = Notice::staticGet($notice_id);
			$replies = new Reply;
			$replies->get('notice_id', $notice_id);

			common_dequeue_notice($notice);
			$replies->delete();
			$notice->delete();
		} else {
			if ($url) {
				common_set_returnto(NULL);
			} else {
				$url = common_local_url('public');
			}
		}
		common_redirect($url);
	}
}
