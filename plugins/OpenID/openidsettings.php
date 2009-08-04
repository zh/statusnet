<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Settings for OpenID
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

require_once INSTALLDIR.'/lib/accountsettingsaction.php';
require_once INSTALLDIR.'/lib/openid.php';

/**
 * Settings for OpenID
 *
 * Lets users add, edit and delete OpenIDs from their account
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class OpenidsettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */

    function title()
    {
        return _('OpenID settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */

    function getInstructions()
    {
        return _('[OpenID](%%doc.openid%%) lets you log into many sites' .
                 ' with the same user account.'.
                 ' Manage your associated OpenIDs from here.');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_openid_add',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('openidsettings')));
        $this->elementStart('fieldset', array('id' => 'settings_openid_add'));
        $this->element('legend', null, _('Add OpenID'));
        $this->hidden('token', common_session_token());
        $this->element('p', 'form_guide',
                       _('If you want to add an OpenID to your account, ' .
                         'enter it in the box below and click "Add".'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('label', array('for' => 'openid_url'),
                       _('OpenID URL'));
        $this->element('input', array('name' => 'openid_url',
                                      'type' => 'text',
                                      'id' => 'openid_url'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->element('input', array('type' => 'submit',
                                      'id' => 'settings_openid_add_action-submit',
                                      'name' => 'add',
                                      'class' => 'submit',
                                      'value' => _('Add')));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');

        $oid = new User_openid();

        $oid->user_id = $user->id;

        $cnt = $oid->find();

        if ($cnt > 0) {

            $this->element('h2', null, _('Remove OpenID'));

            if ($cnt == 1 && !$user->password) {

                $this->element('p', 'form_guide',
                               _('Removing your only OpenID '.
                                 'would make it impossible to log in! ' .
                                 'If you need to remove it, '.
                                 'add another OpenID first.'));

                if ($oid->fetch()) {
                    $this->elementStart('p');
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->elementEnd('p');
                }

            } else {

                $this->element('p', 'form_guide',
                               _('You can remove an OpenID from your account '.
                                 'by clicking the button marked "Remove".'));
                $idx = 0;

                while ($oid->fetch()) {
                    $this->elementStart('form',
                                        array('method' => 'POST',
                                              'id' => 'form_settings_openid_delete' . $idx,
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('openidsettings')));
                    $this->elementStart('fieldset');
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
                                                  'class' => 'submit remove',
                                                  'value' => _('Remove')));
                    $this->elementEnd('fieldset');
                    $this->elementEnd('form');
                    $idx++;
                }
            }
        }
    }

    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
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

        if ($this->arg('add')) {
            $result = oid_authenticate($this->trimmed('openid_url'),
                                       'finishaddopenid');
            if (is_string($result)) { // error message
                $this->showForm($result);
            }
        } else if ($this->arg('remove')) {
            $this->removeOpenid();
        } else {
            $this->showForm(_('Something weird happened.'));
        }
    }

    /**
     * Handles a request to remove an OpenID from the user's account
     *
     * Validates input and, if everything is OK, deletes the OpenID.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */

    function removeOpenid()
    {
        $openid_url = $this->trimmed('openid_url');

        $oid = User_openid::staticGet('canonical', $openid_url);

        if (!$oid) {
            $this->showForm(_('No such OpenID.'));
            return;
        }
        $cur = common_current_user();
        if (!$cur || $oid->user_id != $cur->id) {
            $this->showForm(_('That OpenID does not belong to you.'));
            return;
        }
        $oid->delete();
        $this->showForm(_('OpenID removed.'), true);
        return;
    }
}
