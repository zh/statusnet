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

class ConfirmemailAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_set_returnto($this->self_url());
			common_redirect(common_local_url('login'));
			return;
		}
		$code = $this->trimmed('code');
		if (!$code) {
			$this->client_error(_t('No confirmation code.'));
			return;
		}
		$confirm_email = Confirm_email::staticGet('code', $code);
		if (!$confirm_email) {
			$this->client_error(_t('Confirmation code not found.'));
			return;
		}
		$cur = common_current_user();
		if ($cur->id != $confirm_email->user_id) {
			$this->client_error(_t('That confirmation code is not for you!'));
			return;
		}
		if ($cur->email == $confirm_email->email) {
			$this->client_error(_t('That email address is already confirmed.'));
			return;
		}
		$cur->query('BEGIN');
		$orig_user = clone($cur);
		$cur->email = $confirm_email->email;
		$result = $cur->update($orig_user);
		if (!$result) {
			$this->server_error(_t('Error setting email address.'));
			return;
		}
		$result = $confirm_email->delete();
		if (!$result) {
			$this->server_error(_t('Error deleting code.'));
			return;
		}
		$cur->query('COMMIT');
		common_show_header(_t('Confirm E-mail Address'));
		common_element('p', NULL,
					   _t('The email address "') . $cur->email . 
					   _t('" has been confirmed for your account.'));
		common_show_footer(_t('Confirm E-mail Address'));
	}
}
