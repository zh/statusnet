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

require_once(INSTALLDIR.'/lib/settingsaction.php');
require_once(INSTALLDIR.'/lib/jabber.php');

class ImsettingsAction extends SettingsAction
{

    function get_instructions()
    {
        return _('You can send and receive notices through Jabber/GTalk [instant messages](%%doc.im%%). Configure your address and settings below.');
    }

    function show_form($msg=null, $success=false)
    {
        $user = common_current_user();
        $this->form_header(_('IM Settings'), $msg, $success);
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'imsettings',
                                           'action' =>
                                           common_local_url('imsettings')));
        $this->hidden('token', common_session_token());

        $this->element('h2', null, _('Address'));

        if ($user->jabber) {
            $this->elementStart('p');
            $this->element('span', 'address confirmed', $user->jabber);
            $this->element('span', 'input_instructions',
                           _('Current confirmed Jabber/GTalk address.'));
            $this->hidden('jabber', $user->jabber);
            $this->elementEnd('p');
            $this->submit('remove', _('Remove'));
        } else {
            $confirm = $this->get_confirmation();
            if ($confirm) {
                $this->elementStart('p');
                $this->element('span', 'address unconfirmed', $confirm->address);
                $this->element('span', 'input_instructions',
                                sprintf(_('Awaiting confirmation on this address. Check your Jabber/GTalk account for a message with further instructions. (Did you add %s to your buddy list?)'), jabber_daemon_address()));
                $this->hidden('jabber', $confirm->address);
                $this->elementEnd('p');
                $this->submit('cancel', _('Cancel'));
            } else {
                $this->input('jabber', _('IM Address'),
                             ($this->arg('jabber')) ? $this->arg('jabber') : null,
                         sprintf(_('Jabber or GTalk address, like "UserName@example.org". First, make sure to add %s to your buddy list in your IM client or on GTalk.'), jabber_daemon_address()));
                $this->submit('add', _('Add'));
            }
        }

        $this->element('h2', null, _('Preferences'));

        $this->checkbox('jabbernotify',
                        _('Send me notices through Jabber/GTalk.'),
                        $user->jabbernotify);
        $this->checkbox('updatefrompresence',
                        _('Post a notice when my Jabber/GTalk status changes.'),
                        $user->updatefrompresence);
        $this->checkbox('jabberreplies',
                        _('Send me replies through Jabber/GTalk from people I\'m not subscribed to.'),
                        $user->jabberreplies);
        $this->checkbox('jabbermicroid',
                        _('Publish a MicroID for my Jabber/GTalk address.'),
                        $user->jabbermicroid);
        $this->submit('save', _('Save'));

        $this->elementEnd('form');
        common_show_footer();
    }

    function get_confirmation()
    {
        $user = common_current_user();
        $confirm = new Confirm_address();
        $confirm->user_id = $user->id;
        $confirm->address_type = 'jabber';
        if ($confirm->find(true)) {
            return $confirm;
        } else {
            return null;
        }
    }

    function handle_post()
    {

        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->save_preferences();
        } else if ($this->arg('add')) {
            $this->add_address();
        } else if ($this->arg('cancel')) {
            $this->cancel_confirmation();
        } else if ($this->arg('remove')) {
            $this->remove_address();
        } else {
            $this->show_form(_('Unexpected form submission.'));
        }
    }

    function save_preferences()
    {

        $jabbernotify = $this->boolean('jabbernotify');
        $updatefrompresence = $this->boolean('updatefrompresence');
        $jabberreplies = $this->boolean('jabberreplies');
        $jabbermicroid = $this->boolean('jabbermicroid');

        $user = common_current_user();

        assert(!is_null($user)); # should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->jabbernotify = $jabbernotify;
        $user->updatefrompresence = $updatefrompresence;
        $user->jabberreplies = $jabberreplies;
        $user->jabbermicroid = $jabbermicroid;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            common_server_error(_('Couldn\'t update user.'));
            return;
        }

        $user->query('COMMIT');

        $this->show_form(_('Preferences saved.'), true);
    }

    function add_address()
    {

        $user = common_current_user();

        $jabber = $this->trimmed('jabber');

        # Some validation

        if (!$jabber) {
            $this->show_form(_('No Jabber ID.'));
            return;
        }

        $jabber = jabber_normalize_jid($jabber);

        if (!$jabber) {
            $this->show_form(_('Cannot normalize that Jabber ID'));
            return;
        }
        if (!jabber_valid_base_jid($jabber)) {
            $this->show_form(_('Not a valid Jabber ID'));
            return;
        } else if ($user->jabber == $jabber) {
            $this->show_form(_('That is already your Jabber ID.'));
            return;
        } else if ($this->jabber_exists($jabber)) {
            $this->show_form(_('Jabber ID already belongs to another user.'));
            return;
        }

          $confirm = new Confirm_address();
           $confirm->address = $jabber;
           $confirm->address_type = 'jabber';
           $confirm->user_id = $user->id;
           $confirm->code = common_confirmation_code(64);

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            common_server_error(_('Couldn\'t insert confirmation code.'));
            return;
        }

        if (!common_config('queue', 'enabled')) {
            jabber_confirm_address($confirm->code,
                                   $user->nickname,
                                   $jabber);
        }

        $msg = sprintf(_('A confirmation code was sent to the IM address you added. You must approve %s for sending messages to you.'), jabber_daemon_address());

        $this->show_form($msg, true);
    }

    function cancel_confirmation()
    {
        $jabber = $this->arg('jabber');
        $confirm = $this->get_confirmation();
        if (!$confirm) {
            $this->show_form(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $jabber) {
            $this->show_form(_('That is the wrong IM address.'));
            return;
        }

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            $this->server_error(_('Couldn\'t delete email confirmation.'));
            return;
        }

        $this->show_form(_('Confirmation cancelled.'), true);
    }

    function remove_address()
    {

        $user = common_current_user();
        $jabber = $this->arg('jabber');

        # Maybe an old tab open...?

        if ($user->jabber != $jabber) {
            $this->show_form(_('That is not your Jabber ID.'));
            return;
        }

        $user->query('BEGIN');
        $original = clone($user);
        $user->jabber = null;
        $result = $user->updateKeys($original);
        if (!$result) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            common_server_error(_('Couldn\'t update user.'));
            return;
        }
        $user->query('COMMIT');

        # XXX: unsubscribe to the old address

        $this->show_form(_('The address was removed.'), true);
    }

    function jabber_exists($jabber)
    {
        $user = common_current_user();
        $other = User::staticGet('jabber', $jabber);
        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }
}
