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

class InviteAction extends Action {
	
	function is_readonly() {				
		return false;
	}
	
    function handle($args) {
        parent::handle($args);
		if (!common_logged_in()) {
			$this->client_error(sprintf(_('You must be logged in to invite other users to use %s'),
										common_config('site', 'name')));
			return;
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if ($this->trimmed('preview')) {
				$this->show_preview();
			} else if ($this->trimmed('send')) {
				$this->send_invitation();
			}
		} else {
			$this->show_form();
		}
	}
	
	function show_preview() {
	}
	
	function send_invitation() {
	}
	
	function show_top($error=NULL) {
		if ($error) {
			common_element('p', 'error', $error);
		} else {
			common_element('div', 'instructions',
						   _('Use this form to invite your friends and colleagues to use this service.'));
		}
	}

	function show_form($error=NULL) {
		
		global $config;

		common_show_header(_('Invite new users'), NULL, $error, array($this, 'show_top'));

		common_element_start('form', array('method' => 'post',
										   'id' => 'invite',
										   'action' => common_local_url('invite')));

		common_textarea('addresses', _('Email addresses'),
						$this->trimmed('addresses'),
						_('Addresses of friends to invite (one per line)'));
		
		common_textarea('personal', _('Personal message'),
						$this->trimmed('personal'),
						_('Optionally add a personal message to the invitation.'));
		
		common_submit('preview', _('Preview'));
		
		common_show_footer();
	}
}
