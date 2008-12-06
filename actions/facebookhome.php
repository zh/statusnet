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

require_once(INSTALLDIR.'/lib/facebookaction.php');

class FacebookhomeAction extends FacebookAction {

	function handle($args) {
		parent::handle($args);

		$this->login();
	}

	function login() {

		$user = null;

		$facebook = $this->get_facebook();

		$fbuid = $facebook->require_login();

		# check to see whether there's already a Facebook link for this user
		$flink = Foreign_link::getByForeignID($fbuid, 2); // 2 == Facebook

		if ($flink) {

			$this->display($facebook, $fbuid);

			$user = $flink->getUser();


			$notice = $user->getCurrentNotice();

			echo $this->show_notices($user);


			$this->update_profile_box($facebook, $fbuid, $user);


		} else {

			$nickname = common_canonical_nickname($this->trimmed('nickname'));
			$password = $this->arg('password');

			if ($nickname) {

				if (common_check_user($nickname, $password)) {

					echo '<h2>Successful authentication!</h2>';

					$user = User::staticGet('nickname', $nickname);

					if (!$user) {
						echo '<h2>Couldn\'t get user!</h2>';
						$this->show_login_form();
					}

					$flink = DB_DataObject::factory('foreign_link');
					$flink->user_id = $user->id;
					$flink->foreign_id = $fbuid;
					$flink->service = 2; # Facebook
					$flink->created = common_sql_now();

					# $this->set_flags($flink, $noticesync, $replysync, $friendsync);

					$flink_id = $flink->insert();

					if ($flink_id) {
						echo '<h2>Successfully made Identi.ca -> Facebook link</h2>';
					}

					$this->display($facebook, $fbuid);

					return;
				} else {
					echo '<h2>Fail!</h2>';
				}

			}

			$this->show_login_form();
		}

	}

	function display($facebook, $fbuid) {

		$this->show_header('Home');

		// Greet the currently logged-in user!
		echo "<p>Hello, <fb:name uid=\"$fbuid\" useyou=\"false\" />!</p>";

		$this->show_footer();
	}


	function show_notices($user) {

		$page = $this->trimmed('page');
		if (!$page) {
			$page = 1;
		}

		$notice = $user->noticesWithFriends(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

		echo '<ul id="notices">';

		$cnt = 0;

		while ($notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
			$cnt++;

			if ($cnt > NOTICES_PER_PAGE) {
				break;
			}

			echo $this->render_notice($notice);
		}

		echo '<ul>';

		common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
						  $page, 'all', array('nickname' => $user->nickname));
	}



	function update_profile_box($facebook, $fbuid, $user) {

		$notice = $user->getCurrentNotice();

		$html = $this->render_notice($notice);

		$fbml = "<fb:wide>$html</fb:wide>";
		$fbml .= "<fb:narrow>$html</fb:narrow>";

		$fbml_main = "<fb:narrow>$html</fb:narrow>";


		$facebook->api_client->profile_setFBML(NULL, $fbuid, $fbml, NULL, NULL, $fbml_main);

	}

}
