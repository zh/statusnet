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

class InviteAction extends Action
{

    function isReadOnly()
    {
        return false;
    }

    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            $this->clientError(sprintf(_('You must be logged in to invite other users to use %s'),
                                        common_config('site', 'name')));
            return;
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->send_invitations();
        } else {
            $this->show_form();
        }
    }

    function send_invitations()
    {

        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

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
                $this->send_invitation($email, $user, $personal);
            }
        }

        common_show_header(_('Invitation(s) sent'));
        if ($already) {
            $this->element('p', null, _('You are already subscribed to these users:'));
            $this->elementStart('ul');
            foreach ($already as $other) {
                $this->element('li', null, sprintf(_('%s (%s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($subbed) {
            $this->element('p', null, _('These people are already users and you were automatically subscribed to them:'));
            $this->elementStart('ul');
            foreach ($subbed as $other) {
                $this->element('li', null, sprintf(_('%s (%s)'), $other->nickname, $other->email));
            }
            $this->elementEnd('ul');
        }
        if ($sent) {
            $this->element('p', null, _('Invitation(s) sent to the following people:'));
            $this->elementStart('ul');
            foreach ($sent as $other) {
                $this->element('li', null, $other);
            }
            $this->elementEnd('ul');
            $this->element('p', null, _('You will be notified when your invitees accept the invitation and register on the site. Thanks for growing the community!'));
        }
        common_show_footer();
    }

    function show_top($error=null)
    {
        if ($error) {
            $this->element('p', 'error', $error);
        } else {
            $this->elementStart('div', 'instructions');
            $this->element('p', null,
                           _('Use this form to invite your friends and colleagues to use this service.'));
            $this->elementEnd('div');
        }
    }

    function show_form($error=null)
    {

        global $config;

        common_show_header(_('Invite new users'), null, $error, array($this, 'show_top'));

        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'invite',
                                           'action' => common_local_url('invite')));
        $this->hidden('token', common_session_token());

        $this->textarea('addresses', _('Email addresses'),
                        $this->trimmed('addresses'),
                        _('Addresses of friends to invite (one per line)'));

        $this->textarea('personal', _('Personal message'),
                        $this->trimmed('personal'),
                        _('Optionally add a personal message to the invitation.'));

        $this->submit('send', _('Send'));

        $this->elementEnd('form');

        common_show_footer();
    }

    function send_invitation($email, $user, $personal)
    {

        $profile = $user->getProfile();
        $bestname = $profile->getBestName();

        $sitename = common_config('site', 'name');

        $invite = new Invitation();

        $invite->address = $email;
        $invite->address_type = 'email';
        $invite->code = common_confirmation_code(128);
        $invite->user_id = $user->id;
        $invite->created = common_sql_now();

        if (!$invite->insert()) {
            common_log_db_error($invite, 'INSERT', __FILE__);
            return false;
        }

        $recipients = array($email);

        $headers['From'] = mail_notify_from();
        $headers['To'] = $email;
        $headers['Subject'] = sprintf(_('%1$s has invited you to join them on %2$s'), $bestname, $sitename);

        $body = sprintf(_("%1\$s has invited you to join them on %2\$s (%3\$s).\n\n".
                          "%2\$s is a micro-blogging service that lets you keep up-to-date with people you know and people who interest you.\n\n".
                          "You can also share news about yourself, your thoughts, or your life online with people who know about you. ".
                          "It's also great for meeting new people who share your interests.\n\n".
                          "%1\$s said:\n\n%4\$s\n\n".
                          "You can see %1\$s's profile page on %2\$s here:\n\n".
                          "%5\$s\n\n".
                          "If you'd like to try the service, click on the link below to accept the invitation.\n\n".
                          "%6\$s\n\n".
                          "If not, you can ignore this message. Thanks for your patience and your time.\n\n".
                          "Sincerely, %2\$s\n"),
                        $bestname,
                        $sitename,
                        common_root_url(),
                        $personal,
                        common_local_url('showstream', array('nickname' => $user->nickname)),
                        common_local_url('register', array('code' => $invite->code)));

        mail_send($recipients, $headers, $body);
    }

}
