<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Register a user by their email address
 * 
 * PHP version 5
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
 *
 * @category  Email registration
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Email registration
 *
 * There are four cases where we're called:
 *
 * 1. GET, no arguments. Initial registration; ask for an email address.  
 * 2. POST, email address argument. Initial registration; send an email to confirm.
 * 3. GET, code argument. Confirming an invitation or a registration; look them up,
 *    create the relevant user if possible, login as that user, and 
 *    show a password-entry form.
 * 4. POST, password argument. After confirmation, set the password for the new
 *    user, and redirect to a registration complete action with some instructions.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class EmailregisterAction extends Action
{
    const NEWEMAIL = 1;
    const SETPASSWORD = 2;
    const NEWREGISTER = 3;
    const CONFIRMINVITE = 4;
    const CONFIRMREGISTER = 5;

    const CONFIRMTYPE = 'register';

    protected $user;
    protected $email;
    protected $code;
    protected $invitation;
    protected $confirmation;
    protected $password1;
    protected $password2;
    protected $state;
    protected $error;
    protected $complete;

    function prepare($argarray)
    {
        parent::prepare($argarray);

        if ($this->isPost()) {

            $this->checkSessionToken();

            $this->email = $this->trimmed('email');

            if (!empty($this->email)) {
                $this->email = common_canonical_email($this->email);
                $this->state = self::NEWEMAIL;
            } else {
                $this->state = self::SETPASSWORD;

                $this->code = $this->trimmed('code');

                if (empty($this->code)) {
                    throw new ClientException(_('No confirmation code.'));
                }

                $this->invitation = Invitation::staticGet('code', $this->code);

                if (empty($this->invitation)) {

                    $this->confirmation = Confirm_address::staticGet('code', $this->code);

                    if (empty($this->confirmation)) {
                        throw new ClientException(_('No such confirmation code.'), 403);
                    }
                }

                $this->password1 = $this->trimmed('password1');
                $this->password2 = $this->trimmed('password2');
                
                $this->tos = $this->boolean('tos');
            }
        } else { // GET
            $this->code = $this->trimmed('code');

            if (empty($this->code)) {
                $this->state = self::NEWREGISTER;
            } else {
                $this->invitation = Invitation::staticGet('code', $this->code);
                if (!empty($this->invitation)) {
                    $this->state = self::CONFIRMINVITE;
                } else {
                    $this->state = self::CONFIRMREGISTER;
                    $this->confirmation = Confirm_address::staticGet('code', $this->code);

                    if (empty($this->confirmation)) {
                        throw new ClientException(_('No such confirmation code.'), 405);
                    }
                }
            }
        }

        return true;
    }

    function title()
    {
        switch ($this->state) {
        case self::NEWREGISTER:
        case self::NEWEMAIL:
            // TRANS: Title for registration page.
            return _m('TITLE','Register');
            break;
        case self::SETPASSWORD:
        case self::CONFIRMINVITE:
        case self::CONFIRMREGISTER:
            // TRANS: Title for page where to change password.
            return _m('TITLE','Complete registration');
            break;
        }
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
        switch ($this->state) {
        case self::NEWREGISTER:
            $this->showRegistrationForm();
            break;
        case self::NEWEMAIL:
            $this->registerUser();
            break;
        case self::CONFIRMINVITE:
            $this->confirmRegistration();
            break;
        case self::CONFIRMREGISTER:
            $this->confirmRegistration();
            break;
        case self::SETPASSWORD:
            $this->setPassword();
            break;
        }
        return;
    }

    function showRegistrationForm()
    {
        $this->form = new EmailRegistrationForm($this, $this->email);
        $this->showPage();
    }

    function registerUser()
    {
        $old = User::staticGet('email', $this->email);

        if (!empty($old)) {
            $this->error = sprintf(_('A user with that email address already exists. You can use the '.
                                     '<a href="%s">password recovery</a> tool to recover a missing password.'),
                                   common_local_url('recoverpassword'));
            $this->showRegistrationForm();
            return;
        }

        $valid = false;

        if (Event::handle('StartValidateUserEmail', array(null, $this->email, &$valid))) {
            $valid = Validate::email($this->email, common_config('email', 'check_domain'));
            Event::handle('EndValidateUserEmail', array(null, $this->email, &$valid));
        }

        if (!$valid) {
            $this->error = _('Not a valid email address.');
            $this->showRegistrationForm();
            return;
        }

        $confirm = Confirm_address::getAddress($this->email, self::CONFIRMTYPE);

        if (empty($confirm)) {
            $confirm = Confirm_address::saveNew(null, $this->email, 'register');
            $prompt = sprintf(_('An email was sent to %s to confirm that address. Check your email inbox for instructions.'),
                              $this->email);
        } else {
            $prompt = sprintf(_('The address %s was already registered but not confirmed. The confirmation code was resent.'),
                              $this->email);
        }

        $this->sendConfirmEmail($confirm);

        $this->complete = $prompt;
        
        $this->showPage();
    }

    function confirmRegistration()
    {
        if (!empty($this->invitation)) {
            $email = $this->invitation->address;
        } else if (!empty($this->confirmation)) {
            $email = $this->confirmation->address;
        }
        
        $nickname = $this->nicknameFromEmail($email);

        $this->form = new ConfirmRegistrationForm($this,
                                                  $nickname,
                                                  $email,
                                                  $this->code);
        $this->showPage();
    }

    function setPassword()
    {
        if (!empty($this->invitation)) {
            $email = $this->invitation->address;
        } else if (!empty($this->confirmation)) {
            $email = $this->confirmation->address;
        } else {
            throw new Exception('No confirmation thing.');
        }

        if (!$this->tos) {
            $this->error = _('You must accept the terms of service and privacy policy to register.');
            $nickname = $this->nicknameFromEmail($email);
            $this->form = new ConfirmRegistrationForm($this, $nickname, $this->email, $this->code);
            $this->showPage();
            return;
        }

        $nickname = $this->nicknameFromEmail($email);

        $this->user = User::register(array('nickname' => $nickname,
                                           'email' => $email,
                                           'email_confirmed' => true));

        if (empty($this->user)) {
            throw new Exception("Failed to register user.");
        }

        if (!empty($this->invitation)) {
            $inviter = User::staticGet('id', $this->invitation->user_id);
            if (!empty($inviter)) {
                Subscription::start($inviter->getProfile(),
                                    $user->getProfile());
            }

            $this->invitation->delete();
        } else if (!empty($this->confirmation)) {
            $this->confirmation->delete();
        } else {
            throw new Exception('No confirmation thing.');
        }

        common_redirect(common_local_url('doc', array('title' => 'welcome')),
                        303);
    }

    function sendConfirmEmail($confirm)
    {
        $sitename = common_config('site', 'name');

        $recipients = array($confirm->address);

        $headers['From'] = mail_notify_from();
        $headers['To'] = trim($confirm->address);
        $headers['Subject'] = sprintf(_('Confirm your registration on %1$s'), $sitename);

        $confirmUrl = common_local_url('register', array('code' => $confirm->code));

        common_debug('Confirm URL is ' . $confirmUrl);

        $body = sprintf(_('Someone (probably you) has requested an account on %1$s using this email address.'.
                          "\n".
                          'To confirm the address, click the following URL or copy it into the address bar of your browser.'.
                          "\n".
                          '%2$s'.
                          "\n".
                          'If it was not you, you can safely ignore this message.'),
                        $sitename,
                        $confirmUrl);

        mail_send($recipients, $headers, $body);
    }

    function showContent()
    {
        if ($this->complete) {
            $this->elementStart('p', 'success');
            $this->raw($this->complete);
            $this->elementEnd('p');
        } else {
            if ($this->error) {
                $this->elementStart('p', 'error');
                $this->raw($this->error);
                $this->elementEnd('p');
            }

            if (!empty($this->form)) {
                $this->form->show();
            }
        }
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return false;
    }

    function nicknameFromEmail($email)
    {
        $parts = explode('@', $email);
        
        $nickname = $parts[0];
        
        $nickname = preg_replace('/[^A-Za-z0-9]/', '', $nickname);

        $nickname = Nickname::normalize($nickname);

        $original = $nickname;

        $n = 0;

        while (User::staticGet('nickname', $nickname)) {
            $n++;
            $nickname = $original . $n;
        }

        return $nickname;
    }
}
