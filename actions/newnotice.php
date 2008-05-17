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
			common_user_error(_t('Not logged in.'));
		} else if ($this->arg('METHOD') == 'POST') {
			$id = $this->save_new_notice();

			if ($id) {
				common_broadcast_notices($id);
				common_redirect(common_local_url('shownotice',
												 array('notice' => $id)), 303);
			} else {
				common_server_error(_t('Problem saving notice.'));
			}
		} else {
			$this->show_form();
		}
	}
	
	function save_new_notice() {
		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$notice = DB_DataObject::factory('notice');
		assert($notice);
		$notice->profile_id = $user->id; # user id *is* profile id
		$notice->content = $this->arg('content');
		$notice->created = time();
		return $notice->insert();
	}
	
	function show_form() {
		common_start_element('form', array('id' => 'newnotice', 'method' => 'POST',
										   'action' => common_local_url('newnotice')));
		common_element('span', 'nickname', $profile->nickname);
		common_element('textarea', array('rows' => 4, 'cols' => 80, 'id' => 'content'));
		common_element('input', array('type' => 'submit'), 'Send');
		common_end_element('form');
	}
}