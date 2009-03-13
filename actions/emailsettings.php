<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Settings for email
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Settings
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/accountsettingsaction.php';

/**
 * Settings for email
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Widget
 */

class EmailsettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('Email Settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Manage how you get email from %%site.name%%.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for adding and removing email addresses and setting
     * email preferences.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_email',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('emailsettings')));

        $this->elementStart('fieldset', array('id' => 'settings_email_address'));
        $this->element('legend', null, _('Address'));
        $this->hidden('token', common_session_token());

        if ($user->email) {
            $this->element('p', array('id' => 'form_confirmed'), $user->email);
            $this->element('p', array('class' => 'form_note'), _('Current confirmed email address.'));
            $this->hidden('email', $user->email);
            $this->submit('remove', _('Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $this->element('p', array('id' => 'form_unconfirmed'), $confirm->address);
                $this->element('p', array('class' => 'form_note'),
                                        _('Awaiting confirmation on this address. '.
                                        'Check your inbox (and spam box!) for a message '.
                                        'with further instructions.'));
                $this->hidden('email', $confirm->address);
                $this->submit('cancel', _('Cancel'));
            } else {
                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                $this->input('email', _('Email Address'),
                             ($this->arg('email')) ? $this->arg('email') : null,
                             _('Email address, like "UserName@example.org"'));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                $this->submit('add', _('Add'));
            }
        }
        $this->elementEnd('fieldset');

       if ($user->email) {
            $this->elementStart('fieldset', array('id' => 'settings_email_incoming'));
            $this->element('legend',_('Incoming email'));
            if ($user->incomingemail) {
                $this->elementStart('p');
                $this->element('span', 'address', $user->incomingemail);
                $this->element('span', 'input_instructions',
                               _('Send email to this address to post new notices.'));
                $this->elementEnd('p');
                $this->submit('removeincoming', _('Remove'));
            }

            $this->elementStart('p');
            $this->element('span', 'input_instructions',
                           _('Make a new email address for posting to; '.
                             'cancels the old one.'));
            $this->elementEnd('p');
            $this->submit('newincoming', _('New'));
            $this->elementEnd('fieldset');
        }

        $this->elementStart('fieldset', array('id' => 'settings_email_preferences'));
        $this->element('legend', null, _('Preferences'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('emailnotifysub',
                        _('Send me notices of new subscriptions through email.'),
                        $user->emailnotifysub);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailnotifyfav',
                        _('Send me email when someone '.
                          'adds my notice as a favorite.'),
                        $user->emailnotifyfav);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailnotifymsg',
                        _('Send me email when someone sends me a private message.'),
                        $user->emailnotifymsg);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailnotifyattn',
                        _('Send me email when someone sends me an "@-reply".'),
                        $user->emailnotifyattn);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailnotifynudge',
                        _('Allow friends to nudge me and send me an email.'),
                        $user->emailnotifynudge);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailpost',
                        _('I want to post notices by email.'),
                        $user->emailpost);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('emailmicroid',
                        _('Publish a MicroID for my email address.'),
                        $user->emailmicroid);
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('save', _('Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Gets any existing email address confirmations we're waiting for
     *
     * @return Confirm_address Email address confirmation for user, or null
     */

    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'email';

        if ($confirm->find(true)) {
            return $confirm;
        } else {
            return null;
        }
    }

    /**
     * Handle posts
     *
     * Since there are a lot of different options on the page, we
     * figure out what we're supposed to do based on which button was
     * pushed
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->savePreferences();
        } else if ($this->arg('add')) {
            $this->addAddress();
        } else if ($this->arg('cancel')) {
            $this->cancelConfirmation();
        } else if ($this->arg('remove')) {
            $this->removeAddress();
        } else if ($this->arg('removeincoming')) {
            $this->removeIncoming();
        } else if ($this->arg('newincoming')) {
            $this->newIncoming();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Save email preferences
     *
     * @return void
     */

    function savePreferences()
    {
        $emailnotifysub   = $this->boolean('emailnotifysub');
        $emailnotifyfav   = $this->boolean('emailnotifyfav');
        $emailnotifymsg   = $this->boolean('emailnotifymsg');
        $emailnotifynudge = $this->boolean('emailnotifynudge');
        $emailnotifyattn  = $this->boolean('emailnotifyattn');
        $emailmicroid     = $this->boolean('emailmicroid');
        $emailpost        = $this->boolean('emailpost');

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->emailnotifysub   = $emailnotifysub;
        $user->emailnotifyfav   = $emailnotifyfav;
        $user->emailnotifymsg   = $emailnotifymsg;
        $user->emailnotifynudge = $emailnotifynudge;
        $user->emailnotifyattn  = $emailnotifyattn;
        $user->emailmicroid     = $emailmicroid;
        $user->emailpost        = $emailpost;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }

        $user->query('COMMIT');

        $this->showForm(_('Preferences saved.'), true);
    }

    /**
     * Add the address passed in by the user
     *
     * @return void
     */

    function addAddress()
    {
        $user = common_current_user();

        $email = $this->trimmed('email');

        // Some validation

        if (!$email) {
            $this->showForm(_('No email address.'));
            return;
        }

        $email = common_canonical_email($email);

        if (!$email) {
            $this->showForm(_('Cannot normalize that email address'));
            return;
        }
        if (!Validate::email($email, true)) {
            $this->showForm(_('Not a valid email address'));
            return;
        } else if ($user->email == $email) {
            $this->showForm(_('That is already your email address.'));
            return;
        } else if ($this->emailExists($email)) {
            $this->showForm(_('That email address already belongs '.
                              'to another user.'));
            return;
        }

        $confirm = new Confirm_address();

        $confirm->address      = $email;
        $confirm->address_type = 'email';
        $confirm->user_id      = $user->id;
        $confirm->code         = common_confirmation_code(64);

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            $this->serverError(_('Couldn\'t insert confirmation code.'));
            return;
        }

        mail_confirm_address($user, $confirm->code, $user->nickname, $email);

        $msg = _('A confirmation code was sent to the email address you added. '.
                 'Check your inbox (and spam box!) for the code and instructions '.
                 'on how to use it.');

        $this->showForm($msg, true);
    }

    /**
     * Handle a request to cancel email confirmation
     *
     * @return void
     */

    function cancelConfirmation()
    {
        $email = $this->arg('email');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $email) {
            $this->showForm(_('That is the wrong IM address.'));
            return;
        }

        $result = $confirm->delete();

        if (!$result) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            $this->serverError(_('Couldn\'t delete email confirmation.'));
            return;
        }

        $this->showForm(_('Confirmation cancelled.'), true);
    }

    /**
     * Handle a request to remove an address from the user's account
     *
     * @return void
     */

    function removeAddress()
    {
        $user = common_current_user();

        $email = $this->arg('email');

        // Maybe an old tab open...?

        if ($user->email != $email) {
            $this->showForm(_('That is not your email address.'));
            return;
        }

        $user->query('BEGIN');

        $original = clone($user);

        $user->email = null;

        $result = $user->updateKeys($original);

        if (!$result) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }
        $user->query('COMMIT');

        $this->showForm(_('The address was removed.'), true);
    }

    /**
     * Handle a request to remove an incoming email address
     *
     * @return void
     */

    function removeIncoming()
    {
        $user = common_current_user();

        if (!$user->incomingemail) {
            $this->showForm(_('No incoming email address.'));
            return;
        }

        $orig = clone($user);

        $user->incomingemail = null;

        if (!$user->updateKeys($orig)) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_("Couldn't update user record."));
        }

        $this->showForm(_('Incoming email address removed.'), true);
    }

    /**
     * Generate a new incoming email address
     *
     * @return void
     */

    function newIncoming()
    {
        $user = common_current_user();

        $orig = clone($user);

        $user->incomingemail = mail_new_incoming_address();

        if (!$user->updateKeys($orig)) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_("Couldn't update user record."));
        }

        $this->showForm(_('New incoming email address added.'), true);
    }

    /**
     * Does another user already have this email address?
     *
     * Email addresses are unique for users.
     *
     * @param string $email Address to check
     *
     * @return boolean Whether the email already exists.
     */

    function emailExists($email)
    {
        $user = common_current_user();

        $other = User::staticGet('email', $email);

        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }
}
