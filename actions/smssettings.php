<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Settings for SMS
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

require_once INSTALLDIR.'/lib/settingsaction.php';

/**
 * Settings for SMS
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      SettingsAction
 */

class SmssettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('SMS Settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('You can receive SMS messages through email from %%site.name%%.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for adding and removing SMS phone numbers and setting
     * SMS preferences.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_sms',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('smssettings')));

        $this->elementStart('fieldset');
        $this->element('legend', null, _('Address'));
        $this->hidden('token', common_session_token());

        if ($user->sms) {
            $this->elementStart('p');
            $carrier = $user->getCarrier();
            $this->element('span', 'address confirmed',
                           $user->sms . ' (' . $carrier->name . ')');
            $this->element('span', 'input_instructions',
                           _('Current confirmed SMS-enabled phone number.'));
            $this->hidden('sms', $user->sms);
            $this->hidden('carrier', $user->carrier);
            $this->elementEnd('p');
            $this->submit('remove', _('Remove'));
        } else {
            $confirm = $this->getConfirmation();
            if ($confirm) {
                $carrier = Sms_carrier::staticGet($confirm->address_extra);
                $this->elementStart('p');
                $this->element('span', 'address unconfirmed',
                               $confirm->address . ' (' . $carrier->name . ')');
                $this->element('span', 'input_instructions',
                               _('Awaiting confirmation on this phone number.'));
                $this->hidden('sms', $confirm->address);
                $this->hidden('carrier', $confirm->address_extra);
                $this->elementEnd('p');
                $this->submit('cancel', _('Cancel'));
                $this->input('code', _('Confirmation code'), null,
                             _('Enter the code you received on your phone.'));
                $this->submit('confirm', _('Confirm'));
            } else {
                $this->input('sms', _('SMS Phone number'),
                             ($this->arg('sms')) ? $this->arg('sms') : null,
                             _('Phone number, no punctuation or spaces, '.
                               'with area code'));
                $this->carrierSelect();
                $this->submit('add', _('Add'));
            }
        }
        $this->elementEnd('fieldset');

        if ($user->sms) {
            $this->element('h2', null, _('Incoming email'));

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
        }

        $this->elementStart('fieldset', array('id' => 'sms_preferences'));
        $this->element('legend', null, _('Preferences'));
        $this->checkbox('smsnotify',
                        _('Send me notices through SMS; '.
                          'I understand I may incur '.
                          'exorbitant charges from my carrier.'),
                        $user->smsnotify);

        $this->submit('save', _('Save'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Get a pending confirmation, if any, for this user
     *
     * @return void
     *
     * @todo very similar to EmailsettingsAction::getConfirmation(); refactor?
     */

    function getConfirmation()
    {
        $user = common_current_user();

        $confirm = new Confirm_address();

        $confirm->user_id      = $user->id;
        $confirm->address_type = 'sms';

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
        } else if ($this->arg('removeincoming')) {
            $this->removeIncoming();
        } else if ($this->arg('newincoming')) {
            $this->newIncoming();
        } else if ($this->arg('confirm')) {
            $this->confirmCode();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Handle a request to save preferences
     *
     * Sets the user's SMS preferences in the DB.
     *
     * @return void
     */

    function savePreferences()
    {
        $smsnotify = $this->boolean('smsnotify');

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->smsnotify = $smsnotify;

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
     * Add a new SMS number for confirmation
     *
     * When the user requests a new SMS number, sends a confirmation
     * message.
     *
     * @return void
     */

    function addAddress()
    {
        $user = common_current_user();

        $sms        = $this->trimmed('sms');
        $carrier_id = $this->trimmed('carrier');

        // Some validation

        if (!$sms) {
            $this->showForm(_('No phone number.'));
            return;
        }

        if (!$carrier_id) {
            $this->showForm(_('No carrier selected.'));
            return;
        }

        $sms = common_canonical_sms($sms);

        if ($user->sms == $sms) {
            $this->showForm(_('That is already your phone number.'));
            return;
        } else if ($this->smsExists($sms)) {
            $this->showForm(_('That phone number already belongs to another user.'));
            return;
        }

        $confirm = new Confirm_address();

        $confirm->address       = $sms;
        $confirm->address_extra = $carrier_id;
        $confirm->address_type  = 'sms';
        $confirm->user_id       = $user->id;
        $confirm->code          = common_confirmation_code(40);

        $result = $confirm->insert();

        if ($result === false) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            $this->serverError(_('Couldn\'t insert confirmation code.'));
            return;
        }

        $carrier = Sms_carrier::staticGet($carrier_id);

        mail_confirm_sms($confirm->code,
                         $user->nickname,
                         $carrier->toEmailAddress($sms));

        $msg = _('A confirmation code was sent to the phone number you added. '.
                 'Check your phone for the code and instructions '.
                 'on how to use it.');

        $this->showForm($msg, true);
    }

    /**
     * Cancel a pending confirmation
     *
     * Cancels the confirmation.
     *
     * @return void
     */

    function cancelConfirmation()
    {
        $sms     = $this->trimmed('sms');
        $carrier = $this->trimmed('carrier');

        $confirm = $this->getConfirmation();

        if (!$confirm) {
            $this->showForm(_('No pending confirmation to cancel.'));
            return;
        }
        if ($confirm->address != $sms) {
            $this->showForm(_('That is the wrong confirmation number.'));
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
     * Remove a phone number from the user's account
     *
     * @return void
     */

    function removeAddress()
    {
        $user = common_current_user();

        $sms     = $this->arg('sms');
        $carrier = $this->arg('carrier');

        // Maybe an old tab open...?

        if ($user->sms != $sms) {
            $this->showForm(_('That is not your phone number.'));
            return;
        }

        $user->query('BEGIN');

        $original = clone($user);

        $user->sms      = null;
        $user->carrier  = null;
        $user->smsemail = null;

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
     * Does this sms number exist in our database?
     *
     * Also checks if it belongs to someone else
     *
     * @param string $sms phone number to check
     *
     * @return boolean does the number exist
     */

    function smsExists($sms)
    {
        $user = common_current_user();

        $other = User::staticGet('sms', $sms);

        if (!$other) {
            return false;
        } else {
            return $other->id != $user->id;
        }
    }

    /**
     * Show a drop-down box with all the SMS carriers in the DB
     *
     * @return void
     */

    function carrierSelect()
    {
        $carrier = new Sms_carrier();

        $cnt = $carrier->find();

        $this->elementStart('p');
        $this->element('label', array('for' => 'carrier'));
        $this->elementStart('select', array('name' => 'carrier',
                                            'id' => 'carrier'));
        $this->element('option', array('value' => 0),
                       _('Select a carrier'));
        while ($carrier->fetch()) {
            $this->element('option', array('value' => $carrier->id),
                           $carrier->name);
        }
        $this->elementEnd('select');
        $this->elementEnd('p');
        $this->element('span', 'input_instructions',
                       sprintf(_('Mobile carrier for your phone. '.
                                 'If you know a carrier that accepts ' .
                                 'SMS over email but isn\'t listed here, ' .
                                 'send email to let us know at %s.'),
                               common_config('site', 'email')));
    }

    /**
     * Confirm an SMS confirmation code
     *
     * Redirects to the confirmaddress page for this code
     *
     * @return void
     */

    function confirmCode()
    {
        $code = $this->trimmed('code');

        if (!$code) {
            $this->showForm(_('No code entered'));
            return;
        }

        common_redirect(common_local_url('confirmaddress',
                                         array('code' => $code)));
    }
}
