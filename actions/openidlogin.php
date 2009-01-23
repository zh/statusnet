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

require_once(INSTALLDIR.'/lib/openid.php');

class OpenidloginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);
        if (common_logged_in()) {
            $this->clientError(_('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $openid_url = $this->trimmed('openid_url');

            # CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. Try again, please.'), $openid_url);
                return;
            }

            $rememberme = $this->boolean('rememberme');

            common_ensure_session();

            $_SESSION['openid_rememberme'] = $rememberme;

            $result = oid_authenticate($openid_url,
                                       'finishopenidlogin');

            if (is_string($result)) { # error message
                unset($_SESSION['openid_rememberme']);
                $this->showForm($result, $openid_url);
            }
        } else {
            $openid_url = oid_get_last();
            $this->showForm(null, $openid_url);
        }
    }

    function getInstructions()
    {
        return _('Login with an [OpenID](%%doc.openid%%) account.');
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $instr = $this->getInstructions();
            $output = common_markup_to_html($instr);
            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    function title()
    {
        return _('OpenID Login');
    }

    function showForm($error=null, $openid_url)
    {
        $this->error = $error;
        $this->openid_url = $openid_url;
        $this->showPage();
    }

    function showContent() {
        $formaction = common_local_url('openidlogin');
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'openidlogin',
                                           'action' => $formaction));
        $this->hidden('token', common_session_token());
        $this->input('openid_url', _('OpenID URL'),
                     $this->openid_url,
                     _('Your OpenID URL'));
        $this->checkbox('rememberme', _('Remember me'), false,
                        _('Automatically login in the future; ' .
                           'not for shared computers!'));
        $this->submit('submit', _('Login'));
        $this->elementEnd('form');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
