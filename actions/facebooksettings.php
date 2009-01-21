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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/lib/facebookaction.php';

class FacebooksettingsAction extends FacebookAction
{

    function handle($args)
    {
        parent::handle($args);

        if ($this->arg('save')) {
            $this->saveSettings();
        } else {
            $this->showForm();
        }
    }

    function saveSettings() {

        $noticesync = $this->arg('noticesync');
        $replysync = $this->arg('replysync');
        $prefix = $this->trimmed('prefix');

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        $original = clone($flink);
        $flink->set_flags($noticesync, $replysync, false);
        $result = $flink->update($original);

        $facebook->api_client->data_setUserPreference(FACEBOOK_NOTICE_PREFIX,
            substr($prefix, 0, 128));

        if ($result) {
            $this->showForm('Sync preferences saved.', true);
        } else {
            $this->showForm('There was a problem saving your sync preferences!');
        }
    }

    function showForm($msg = null, $success = false) {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        $this->showHeader($msg, $success);
        $this->showNav('Settings');

        if ($facebook->api_client->users_hasAppPermission('status_update')) {

            $this->elementStart('form', array('method' => 'post',
                                               'id' => 'facebook_settings'));

            $this->element('h2', null, _('Sync preferences'));

            $this->checkbox('noticesync', _('Automatically update my Facebook status with my notices.'),
                                ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND) : true);

            $this->checkbox('replysync', _('Send "@" replies to Facebook.'),
                             ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true);

            $prefix = $facebook->api_client->data_getUserPreference(1);

            $this->input('prefix', _('Prefix'),
                         ($prefix) ? $prefix : null,
                         _('A string to prefix notices with.'));
            $this->submit('save', _('Save'));

            $this->elementEnd('form');

        } else {

            // Figure what the URL of our app is.
            $app_props = $facebook->api_client->Admin_getAppProperties(
                    array('canvas_name', 'application_name'));
            $app_url = 'http://apps.facebook.com/' . $app_props['canvas_name'] . '/settings.php';
            $app_name = $app_props['application_name'];

            $instructions = sprintf(_('If you would like the %s app to automatically update ' .
                'your Facebook status with your latest notice, you need ' .
                'to give it permission.'), $app_name);

            $this->elementStart('p');
            $this->element('span', array('id' => 'permissions_notice'), $instructions);
            $this->elementEnd('p');

            $this->elementStart('ul', array('id' => 'fb-permissions-list'));
            $this->elementStart('li', array('id' => 'fb-permissions-item'));
            $this->elementStart('fb:prompt-permission', array('perms' => 'status_update',
                'next_fbjs' => 'document.setLocation(\'' . $app_url . '\')'));
            $this->element('span', array('class' => 'facebook-button'),
                _('Allow Identi.ca to update my Facebook status'));
            $this->elementEnd('fb:prompt-permission');
            $this->elementEnd('li');
            $this->elementEnd('ul');
        }

        $this->showFooter();
    }

}
