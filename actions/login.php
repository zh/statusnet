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

class LoginAction extends Action
{

    function isReadOnly()
    {
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if (common_is_real_login()) {
            $this->clientError(_('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->checkLogin();
        } else {
            $this->showForm();
        }
    }

    function checkLogin()
    {
        # XXX: login throttle

        # CSRF protection - token set in common_notice_form()
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $nickname = common_canonical_nickname($this->trimmed('nickname'));
        $password = $this->arg('password');
        if (common_check_user($nickname, $password)) {
            # success!
            if (!common_set_user($nickname)) {
                $this->serverError(_('Error setting user.'));
                return;
            }
            common_real_login(true);
            if ($this->boolean('rememberme')) {
                common_debug('Adding rememberme cookie for ' . $nickname);
                common_rememberme();
            }
            # success!
            $url = common_get_returnto();
            if ($url) {
                # We don't have to return to it again
                common_set_returnto(null);
            } else {
                $url = common_local_url('all',
                                        array('nickname' =>
                                              $nickname));
            }
            common_redirect($url);
        } else {
            $this->showForm(_('Incorrect username or password.'));
            return;
        }

        # success!
        if (!common_set_user($user)) {
            $this->serverError(_('Error setting user.'));
            return;
        }

        common_real_login(true);

        if ($this->boolean('rememberme')) {
            common_debug('Adding rememberme cookie for ' . $nickname);
            common_rememberme($user);
        }
        # success!
        $url = common_get_returnto();
        if ($url) {
            # We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $nickname));
        }
        common_redirect($url);
    }

    function showForm($error=null)
    {
	$this->error = $error;
	$this->showPage();
    }

    function title()
    {
	return _('Login');
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $instr = $this->getInstructions();
            $output = common_markup_to_html($instr);
	    $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }
    
    function showContent()
    {      
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'login',
                                           'action' => common_local_url('login')));
        $this->input('nickname', _('Nickname'));
        $this->password('password', _('Password'));
        $this->checkbox('rememberme', _('Remember me'), false,
                        _('Automatically login in the future; ' .
                           'not for shared computers!'));
        $this->submit('submit', _('Login'));
        $this->hidden('token', common_session_token());
        $this->elementEnd('form');
        $this->elementStart('p');
        $this->element('a', array('href' => common_local_url('recoverpassword')),
                       _('Lost or forgotten password?'));
        $this->elementEnd('p');
    }

    function getInstructions()
    {
        if (common_logged_in() &&
            !common_is_real_login() &&
            common_get_returnto())
        {
            # rememberme logins have to reauthenticate before
            # changing any profile settings (cookie-stealing protection)
            return _('For security reasons, please re-enter your ' .
                     'user name and password ' .
                     'before changing your settings.');
        } else {
            return _('Login with your username and password. ' .
                     'Don\'t have a username yet? ' .
                     '[Register](%%action.register%%) a new account, or ' .
                     'try [OpenID](%%action.openidlogin%%). ');
        }
    }
}
