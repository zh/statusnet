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
			$this->send_invitations();
		} else {
			$this->show_form();
		}
	}

	function send_invitations() {

		$user = common_current_user();
		$profile = $user->getProfile();

		$bestname = $profile->getBestName();
		$sitename = common_config('site', 'name');
		$personal = $this->trimmed('personal');

		$addresses = explode("\n", $this->trimmed('addresses'));

		foreach ($addresses as $email) {
			$email = trim($email);
			if (!Validate::email($email, true)) {
				$this->show_form(sprintf(_('Invalid email address: %s'), $email));
				return;
			}
		}

		$already = array();
		$subbed = array();

		foreach ($addresses as $email) {
			$email = common_canonical_email($email);
			$other = User::staticGet('email', $email);
			if ($other) {
				if ($user->isSubscribed($other)) {
					$already[] = $other;
				} else {
					subs_subscribe_to($user, $other);
					$subbed[] = $other;
				}
			} else {
				$sent[] = $email;
				$this->send_invitation($email, $user);
			}
		}

		common_show_header(_('Invitation(s) sent'));
		if ($already) {
			common_element('p', NULL, _('You are already subscribed to these users:'));
			common_element_start('ul');
			foreach ($already as $other) {
				common_element('li', NULL, sprintf(_('%s (%s)'), $other->nickname, $other->email));
			}
			common_element_end('ul');
		}
		if ($subbed) {
			common_element('p', NULL, _('These people are already users and you were automatically subscribed to them:'));
			common_element_start('ul');
			foreach ($subbed as $other) {
				common_element('li', NULL, sprintf(_('%s (%s)'), $other->nickname, $other->email));
			}
			common_element_end('ul');
		}
		if ($sent) {
			common_element('p', NULL, _('Invitation(s) sent to the following people:'));
			common_element_start('ul');
			foreach ($sent as $other) {
				common_element('li', NULL, $sent);
			}
			common_element_end('ul');
			common_element('p', NULL, _('You will be notified when your invitees accept the invitation and register on the site. Thanks for growing the community!'));
		}
		common_show_footer();
	}

	function show_top($error=NULL) {
		if ($error) {
			common_element('p', 'error', $error);
		} else {
			common_element_start('div', 'instructions');
			common_element('p', NULL,
						   _('Use this form to invite your friends and colleagues to use this service.'));
			common_element_end('div');
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

		common_element_end('form');

		common_show_footer();
	}

	function send_invitation($email, $user) {

		$email = trim($email);

		$invite = new Invitation();

		$invite->address = $email;
		$invite->type = 'email';
		$invite->user_id = $user->id;
		$invite->created = common_sql_now();

		if (!$invite->insert()) {
			common_log_db_error($invite, 'INSERT', __FILE__);
			return false;
		}

		$recipients = array($email);

		$headers['From'] = mail_notify_from();
		$headers['To'] = $email;
		$headers['Subject'] = sprintf(_('%1s has invited you to join them on %2s'), $bestname, $sitename);

		$body = sprintf(_("%1s has invited you to join them on %2s (%3s).\n\n".
						  "%4s is a micro-blogging service that lets you keep up-to-date with people you know and people who interest you.\n\n".
						  "You can also share news about yourself, your thoughts, or your life online with people who know about you.\n\n".
						  "%5s said:\n\n%6s\n\n".
						  "You can see %7s's profile page on %8s here:\n\n".
						  "%9s\n\n".
						  "If you'd like to try the service, click on the link below to accept the invitation.\n\n".
						  "%10s\n\n".
						  "If not, you can ignore this message. Thanks for your patience and your time.\n\n".
						  "Sincerely, %11s\n"),
						$bestname, $sitename, common_root_url(),
						$sitename,
						$bestname, $personal,
						$bestname, $sitename,
						common_local_url('showstream', array('nickname' => $user->nickname)),
						common_local_url('register', array('code' => $invite->code)),
						$sitename);

		mail_send($recipients, $headers, $body);
	}

}
