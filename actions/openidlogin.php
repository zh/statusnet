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

require_once(INSTALLDIR.'/lib/openid.php');

class OpenidloginAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (common_logged_in()) {
			common_user_error(_t('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$openid_url = $this->trimmed('openid_url');
			$result = oid_authenticate($openid_url,
									   'finishopenidlogin');
			if (is_string($result)) { # error message
				$this->show_form($result, $openid_url);
			}
		} else {
			$openid_url = oid_get_last();
			$this->show_form(NULL, $openid_url);
		}
	}

	function get_instructions() {
		return _t('Login with an [OpenID](%%doc.openid%%) account.');
	}

	function show_top($error=NULL) {
		if ($error) {
			common_element('div', array('class' => 'error'), $error);
		} else {
			$instr = $this->get_instructions();
			$output = common_markup_to_html($instr);
			common_element_start('div', 'instructions');
			common_raw($output);
			common_element_end('div');
		}
	}

	function show_form($error=NULL, $openid_url) {
		common_show_header(_t('OpenID Login'), NULL, $error, array($this, 'show_top'));
		$formaction = common_local_url('openidlogin');
		common_element_start('form', array('method' => 'post',
										   'id' => 'openidlogin',
										   'action' => $formaction));
		common_input('openid_url', _t('OpenID URL'),
					 $openid_url,
					 _t('Your OpenID URL'));
		common_submit('submit', _t('Login'));
		common_element_end('form');
		common_show_footer();
	}
}
