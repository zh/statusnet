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
require_once(INSTALLDIR.'/lib/openid.php');

class OpenidsettingsAction extends SettingsAction
{

    function get_instructions()
    {
        return _('[OpenID](%%doc.openid%%) lets you log into many sites ' .
                  ' with the same user account. '.
                  ' Manage your associated OpenIDs from here.');
    }

    function show_form($msg=null, $success=false)
    {

        $user = common_current_user();

        $this->form_header(_('OpenID settings'), $msg, $success);

        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'openidadd',
                                           'action' =>
                                           common_local_url('openidsettings')));
        $this->hidden('token', common_session_token());
        $this->element('h2', null, _('Add OpenID'));
        $this->element('p', null,
                       _('If you want to add an OpenID to your account, ' .
                          'enter it in the box below and click "Add".'));
        $this->elementStart('p');
        $this->element('label', array('for' => 'openid_url'),
                       _('OpenID URL'));
        $this->element('input', array('name' => 'openid_url',
                                      'type' => 'text',
                                      'id' => 'openid_url'));
        $this->element('input', array('type' => 'submit',
                                      'id' => 'add',
                                      'name' => 'add',
                                      'class' => 'submit',
                                      'value' => _('Add')));
        $this->elementEnd('p');
        $this->elementEnd('form');

        $oid = new User_openid();
        $oid->user_id = $user->id;

        $cnt = $oid->find();

        if ($cnt > 0) {

            $this->element('h2', null, _('Remove OpenID'));

            if ($cnt == 1 && !$user->password) {

                $this->element('p', null,
                               _('Removing your only OpenID would make it impossible to log in! ' .
                                  'If you need to remove it, add another OpenID first.'));

                if ($oid->fetch()) {
                    $this->elementStart('p');
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->elementEnd('p');
                }

            } else {

                $this->element('p', null,
                               _('You can remove an OpenID from your account '.
                                  'by clicking the button marked "Remove".'));
                $idx = 0;

                while ($oid->fetch()) {
                    $this->elementStart('form', array('method' => 'POST',
                                                       'id' => 'openiddelete' . $idx,
                                                       'action' =>
                                                       common_local_url('openidsettings')));
                    $this->elementStart('p');
                    $this->hidden('token', common_session_token());
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->element('input', array('type' => 'hidden',
                                                  'id' => 'openid_url'.$idx,
                                                  'name' => 'openid_url',
                                                  'value' => $oid->canonical));
                    $this->element('input', array('type' => 'submit',
                                                  'id' => 'remove'.$idx,
                                                  'name' => 'remove',
                                                  'class' => 'submit',
                                                  'value' => _('Remove')));
                    $this->elementEnd('p');
                    $this->elementEnd('form');
                    $idx++;
                }
            }
        }

        common_show_footer();
    }

    function handle_post()
    {
        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if ($this->arg('add')) {
            $result = oid_authenticate($this->trimmed('openid_url'), 'finishaddopenid');
            if (is_string($result)) { # error message
                $this->show_form($result);
            }
        } else if ($this->arg('remove')) {
            $this->remove_openid();
        } else {
            $this->show_form(_('Something weird happened.'));
        }
    }

    function remove_openid()
    {

        $openid_url = $this->trimmed('openid_url');
        $oid = User_openid::staticGet('canonical', $openid_url);
        if (!$oid) {
            $this->show_form(_('No such OpenID.'));
            return;
        }
        $cur = common_current_user();
        if (!$cur || $oid->user_id != $cur->id) {
            $this->show_form(_('That OpenID does not belong to you.'));
            return;
        }
        $oid->delete();
        $this->show_form(_('OpenID removed.'), true);
        return;
    }
}
