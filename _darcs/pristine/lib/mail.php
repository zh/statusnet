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

require_once('Mail.php');

function mail_backend() {
	static $backend = NULL;

	if (!$backend) {
		global $config;
		$backend = Mail::factory($config['mail']['backend'],
								 ($config['mail']['params']) ? $config['mail']['params'] : array());
		if (PEAR::isError($backend)) {
			common_server_error($backend->getMessage(), 500);
		}
	}
	return $backend;
}

# XXX: use Mail_Queue... maybe

function mail_send($recipients, $headers, $body) {
	$backend = mail_backend();
    if (!isset($headers['Content-Type'])) {
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
    }
	assert($backend); # throws an error if it's bad
	$sent = $backend->send($recipients, $headers, $body);
	if (PEAR::isError($sent)) {
		common_log(LOG_ERR, 'Email error: ' . $sent->getMessage());
		return false;
	}
	return true;
}

function mail_domain() {
	$maildomain = common_config('mail', 'domain');
	if (!$maildomain) {
		$maildomain = common_config('site', 'server');
	}
	return $maildomain;
}

function mail_notify_from() {
	$notifyfrom = common_config('mail', 'notifyfrom');
	if (!$notifyfrom) {
		$domain = mail_domain();
		$notifyfrom = common_config('site', 'name') .' <noreply@'.$domain.'>';
	}
	return $notifyfrom;
}

function mail_to_user(&$user, $subject, $body, $address=NULL) {
	if (!$address) {
		$address = $user->email;
	}

	$recipients = $address;
	$profile = $user->getProfile();

	$headers['From'] = mail_notify_from();
	$headers['To'] = $profile->getBestName() . ' <' . $address . '>';
	$headers['Subject'] = $subject;

	return mail_send($recipients, $headers, $body);
}

# For confirming a Jabber address

function mail_confirm_address($user, $code, $nickname, $address) {

	$subject = _('Email address confirmation');

    $body = sprintf(_("Hey, %s.\n\nSomeone just entered this email address on %s.\n\n" .
        "If it was you, and you want to confirm your entry, use the URL below:\n\n\t%s\n\n" .
        "If not, just ignore this message.\n\nThanks for your time, \n%s\n")
        , $nickname, common_config('site', 'name')
        , common_local_url('confirmaddress', array('code' => $code)), common_config('site', 'name'));
     return mail_to_user($user, $subject, $body, $address);
}

function mail_subscribe_notify($listenee, $listener) {
	$other = $listener->getProfile();
	mail_subscribe_notify_profile($listenee, $other);
}

function mail_subscribe_notify_profile($listenee, $other) {
	if ($listenee->email && $listenee->emailnotifysub) {
        // use the recipients localization
        common_init_locale($listenee->language);
		$profile = $listenee->getProfile();
		$name = $profile->getBestName();
		$long_name = ($other->fullname) ? ($other->fullname . ' (' . $other->nickname . ')') : $other->nickname;
		$recipients = $listenee->email;
		$headers['From'] = mail_notify_from();
		$headers['To'] = $name . ' <' . $listenee->email . '>';
		$headers['Subject'] = sprintf(_('%1$s is now listening to your notices on %2$s.'),
                                      $other->getBestName(),
									  common_config('site', 'name'));
		$body  = sprintf(_('%1$s is now listening to your notices on %2$s.'."\n\n".
						   "\t".'%3$s'."\n\n".
						   '%4$s'.
                           '%5$s'.
                           '%6$s'.
						   "\n".'Faithfully yours,'."\n".'%7$s.'."\n\n".
                           "----\n".
                           "Change your email address or notification options at %8$s"),
                         $long_name,
                         common_config('site', 'name'),
                         $other->profileurl,
                         ($other->location) ? sprintf(_("Location: %s\n"), $other->location) : '',
                         ($other->homepage) ? sprintf(_("Homepage: %s\n"), $other->homepage) : '',
                         ($other->bio) ? sprintf(_("Bio: %s\n\n"), $other->bio) : '',
                         common_config('site', 'name'),
                         common_local_url('emailsettings'));
        // reset localization
        common_init_locale();
		mail_send($recipients, $headers, $body);
	}
}

function mail_new_incoming_notify($user) {

	$profile = $user->getProfile();
	$name = $profile->getBestName();

	$headers['From'] = $user->incomingemail;
	$headers['To'] = $name . ' <' . $user->email . '>';
	$headers['Subject'] = sprintf(_('New email address for posting to %s'),
								  common_config('site', 'name'));

	$body  = sprintf(_("You have a new posting address on %1\$s.\n\n".
					   "Send email to %2\$s to post new messages.\n\n".
					   "More email instructions at %3\$s.\n\n".
					   "Faithfully yours,\n%4\$s"),
					 common_config('site', 'name'),
					 $user->incomingemail,
					 common_local_url('doc', array('title' => 'email')),
					 common_config('site', 'name'));

	mail_send($user->email, $headers, $body);
}

function mail_new_incoming_address() {
	$prefix = common_confirmation_code(64);
	$suffix = mail_domain();
	return $prefix . '@' . $suffix;
}

function mail_broadcast_notice_sms($notice) {

    # Now, get users subscribed to this profile

	$user = new User();

	$user->query('SELECT nickname, smsemail, incomingemail ' .
				 'FROM user JOIN subscription ' .
				 'ON user.id = subscription.subscriber ' .
				 'WHERE subscription.subscribed = ' . $notice->profile_id . ' ' .
				 'AND user.smsemail IS NOT NULL ' .
				 'AND user.smsnotify = 1 ' .
                 'AND subscription.sms = 1 ');

	while ($user->fetch()) {
		common_log(LOG_INFO,
				   'Sending notice ' . $notice->id . ' to ' . $user->smsemail,
				   __FILE__);
		$success = mail_send_sms_notice_address($notice, $user->smsemail, $user->incomingemail);
		if (!$success) {
			# XXX: Not sure, but I think that's the right thing to do
			common_log(LOG_WARNING,
					   'Sending notice ' . $notice->id . ' to ' . $user->smsemail . ' FAILED, cancelling.',
					   __FILE__);
			return false;
		}
	}

	$user->free();
	unset($user);

	return true;
}

function mail_send_sms_notice($notice, $user) {
	return mail_send_sms_notice_address($notice, $user->smsemail, $user->incomingemail);
}

function mail_send_sms_notice_address($notice, $smsemail, $incomingemail) {

	$to = $nickname . ' <' . $smsemail . '>';
	$other = $notice->getProfile();

	common_log(LOG_INFO, "Sending notice " . $notice->id . " to " . $smsemail, __FILE__);

	$headers = array();
	$headers['From'] = (isset($incomingemail)) ? $incomingemail : mail_notify_from();
	$headers['To'] = $to;
	$headers['Subject'] = sprintf(_('%s status'),
								  $other->getBestName());
	$body = $notice->content;

	return mail_send($smsemail, $headers, $body);
}

function mail_confirm_sms($code, $nickname, $address) {

	$recipients = $address;

	$headers['From'] = mail_notify_from();
	$headers['To'] = $nickname . ' <' . $address . '>';
	$headers['Subject'] = _('SMS confirmation');

	$body = "$nickname: confirm you own this phone number with this code:";
	$body .= "\n\n";
	$body .= $code;
	$body .= "\n\n";

	mail_send($recipients, $headers, $body);
}

function mail_notify_nudge($from, $to) {
    common_init_locale($to->language);
	$subject = sprintf(_('You\'ve been nudged by %s'), $from->nickname);

	$from_profile = $from->getProfile();

	$body = sprintf(_("%1\$s (%2\$s) is wondering what you are up to these days and is inviting you to post some news.\n\n".
					  "So let's hear from you :)\n\n".
					  "%3\$s\n\n".
					  "Don't reply to this email; it won't get to them.\n\n".
					  "With kind regards,\n".
					  "%4\$s\n"),
					$from_profile->getBestName(),
					$from->nickname,
					common_local_url('all', array('nickname' => $to->nickname)),
					common_config('site', 'name'));
    common_init_locale();
	return mail_to_user($to, $subject, $body);
}

function mail_notify_message($message, $from=NULL, $to=NULL) {

	if (is_null($from)) {
		$from = User::staticGet('id', $message->from_profile);
	}

	if (is_null($to)) {
		$to = User::staticGet('id', $message->to_profile);
	}

	if (is_null($to->email) || !$to->emailnotifymsg) {
		return true;
	}

    common_init_locale($to->language);
	$subject = sprintf(_('New private message from %s'), $from->nickname);

	$from_profile = $from->getProfile();

	$body = sprintf(_("%1\$s (%2\$s) sent you a private message:\n\n".
					  "------------------------------------------------------\n".
					  "%3\$s\n".
					  "------------------------------------------------------\n\n".
					  "You can reply to their message here:\n\n".
					  "%4\$s\n\n".
					  "Don't reply to this email; it won't get to them.\n\n".
					  "With kind regards,\n".
					  "%5\$s\n"),
					$from_profile->getBestName(),
					$from->nickname,
					$message->content,
					common_local_url('newmessage', array('to' => $from->id)),
					common_config('site', 'name'));

    common_init_locale();
	return mail_to_user($to, $subject, $body);
}

function mail_notify_fave($other, $user, $notice) {

	$profile = $user->getProfile();
	$bestname = $profile->getBestName();
    common_init_locale($other->language);
	$subject = sprintf(_('%s added your notice as a favorite'), $bestname);
	$body = sprintf(_("%1\$s just added your notice from %2\$s as one of their favorites.\n\n" .
					  "In case you forgot, you can see the text of your notice here:\n\n" .
					  "%3\$s\n\n" .
					  "You can see the list of %1\$s's favorites here:\n\n" .
					  "%4\$s\n\n" .
					  "Faithfully yours,\n" .
					  "%5\$s\n"),
					$bestname,
					common_exact_date($notice->created),
					common_local_url('shownotice', array('notice' => $notice->id)),
					common_local_url('showfavorites', array('nickname' => $user->nickname)),
					common_config('site', 'name'));

    common_init_locale();
	mail_to_user($other, $subject, $body);
}
