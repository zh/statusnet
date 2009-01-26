<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Settings for Jabber/XMPP integration
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
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/connectsettingsaction.php';
require_once INSTALLDIR.'/lib/jabber.php';

/**
 * Settings for Jabber/XMPP integration
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      SettingsAction
 */

class ImsettingsAction extends ConnectSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('IM Settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('You can send and receive notices through '.
                 'Jabber/GTalk [instant messages](%%doc.im%%). '.
                 'Configure your address and settings below.');
    }

    /**
     * Content area of the page
     *
     * We make different sections of the form for the different kinds of
     * functions, and have submit buttons with different names. These
     * are muxed by handlePost() to see what the user really wants to do.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_im',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('imsettings')));
        $this->elementStart('fieldset', array('id' => 'settings_im_address'));
        $this->element('legend', null, _('Address'));
        $this->hidden('token', common_session_token());

        if ($user->jabber) {
            $this->element('p', 'form_confirmed', $user->jabber);
            $this->element('p', 'form_note',
                           _('Current confirmed Jabber/GTalk address.'));
            $this->hidden('jabber', $user->jabber);
            $this->submit('remove', _('Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $this->element('p', 'form_unconfirmed', $confirm->address);
                $this->element('p', 'form_note',
                               sprintf(_('Awaiting confirmation on this address. '.
                                         'Check your Jabber/GTalk account for a '.
                                         'message with further instructions. '.
                                         '(Did you add %s to your buddy list?)'),
                                       jabber_daemon_address()));
                $this->hidden('jabber', $confirm->address);
                $this->submit('cancel', _('Cancel'));
            } else {
                $this->elementStart('ul', 'form_data');
                $this->elementStart('li');
                $this->input('jabber', _('IM Address'),
                             ($this->arg('jabber')) ? $this->arg('jabber') : null,
                             sprintf(_('Jabber or GTalk address, '.
                                       'like "UserName@example.org". '.
                                       'First, make sure to add %s to your '.
                                       'buddy list in your IM client or on GTalk.'),
                                     jabber_daemon_address()));
                $this->elementEnd('li');
                $this->elementEnd('ul');
                $this->submit('add', _('Add'));
            }
        }
        $this->elementEnd('fieldset');
        
        $this->elementStart('fieldset', array('id' => 'settings_im_preferences'));
        $this->element('legend', null, _('Preferences'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('jabbernotify',
                        _('Send me notices through Jabber/GTalk.'),
                        $user->jabbernotify);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('updatefrompresence',
                        _('Post a notice when my Jabber/GTalk status changes.'),
                        $user->updatefrompresence);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('jabberreplies',
                        _('Send me replies through Jabber/GTalk '.
                          'from people I\'m not subscribed to.'),
                        $user->jabberreplies);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('jabbermicroid',
                        _('Publish a MicroID for my Jabber/GTalk address.'),
                        $user->jabbermicroid);
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('save', _('Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Get a confirmation code for this user
     *
     * @return Confirm_address address object for this user
     */

    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'jabber';

        if ($confirm->find(true)) {
            return $confirm;
        } else {
            return null;
        }
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
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
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Save user's Jabber preferences
     *
     * These are the checkboxes at the bottom of the page. They're used to
     * set different settings
     *
     * @return void
     */

    function savePreferences()
    {

        $jabbernotify       = $this->boolean('jabbernotify');
        $updatefrompresence = $this->boolean('updatefrompresence');
        $jabberreplies      = $this->boolean('jabberreplies');
        $jabbermicroid      = $this->boolean('jabbermicroid');

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->jabbernotify       = $jabbernotify;
        $user->updatefrompresence = $updatefrompresence;
        $user->jabberreplies      = $jabberreplies;
        $user->jabbermicroid      = $jabbermicroid;

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
     * Sends a confirmation to the address given
     *
     * Stores a confirmation record and sends out a
     * Jabber message with the confirmation info.
     *
     * @return void
     */

    function addAddress()
    {
        $user = common_current_user();

        $jabber = $this->trimmed('jabber');

        // Some validation

        if (!$jabber) {
            $this->showForm(_('No Jabber ID.'));
            return;
        }

        $jabber = jabber_normalize_jid($jabber);

        if (!$jabber) {
            $this->showForm(_('Cannot normalize that Jabber ID'));
            return;
        }
        if (!jabber_valid_base_jid($jabber)) {
            $this->showForm(_('Not a valid Jabber ID'));
            return;
        } else if ($user->jabber == $jabber) {
            $this->showForm(_('That is already your Jabber ID.'));
            return;
        } else if ($this->jabberExists($jabber)) {
            $this->showForm(_('Jabber ID already belongs to another user.'));
            return;
        }

        $confirm = new Confirm_address();

        $confirm->address      = $jabber;
        $confirm->address_type = 'jabber';
        $confirm->user_id      = $user->id;
        $confirm->code         = common_confirmation_code(64);

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            $this->serverError(_('Couldn\'t insert confirmation code.'));
            return;
        }

        if (!common_config('queue', 'enabled')) {
            jabber_confirm_address($confirm->code,
                                   $user->nickname,
                                   $jabber);
        }

        $msg = sprintf(_('A confirmation code was sent '.
                         'to the IM address you added. '.
                         'You must approve %s for '.
                         'sending messages to you.'),
                       jabber_daemon_address());

        $this->showForm($msg, true);
    }

    /**
     * Cancel a confirmation
     *
     * If a confirmation exists, cancel it.
     *
     * @return void
     */

    function cancelConfirmation()
    {
        $jabber = $this->arg('jabber');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $jabber) {
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
     * Remove an address
     *
     * If the user has a confirmed address, remove it.
     *
     * @return void
     */

    function removeAddress()
    {
        $user = common_current_user();

        $jabber = $this->arg('jabber');

        // Maybe an old tab open...?

        if ($user->jabber != $jabber) {
            $this->showForm(_('That is not your Jabber ID.'));
            return;
        }

        $user->query('BEGIN');

        $original = clone($user);

        $user->jabber = null;

        $result = $user->updateKeys($original);

        if (!$result) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }
        $user->query('COMMIT');

        // XXX: unsubscribe to the old address

        $this->showForm(_('The address was removed.'), true);
    }

    /**
     * Does this Jabber ID exist?
     *
     * Checks if we already have another user with this address.
     *
     * @param string $jabber Address to check
     *
     * @return boolean whether the Jabber ID exists
     */

    function jabberExists($jabber)
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
