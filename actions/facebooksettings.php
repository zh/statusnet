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

require_once(INSTALLDIR.'/lib/facebookaction.php');

class FacebooksettingsAction extends FacebookAction
{

    function handle($args)
    {
        parent::handle($args);

        if ($this->arg('save')) {
            $this->save_settings();
        } else {
            $this->show_form();
        }
    }

    function save_settings() {

        $noticesync = $this->arg('noticesync');
        $replysync = $this->arg('replysync');
        $prefix = $this->trimmed('prefix');

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        $original = clone($flink);
        $flink->set_flags($noticesync, $replysync, false);
        $result = $flink->update($original);

        $facebook->api_client->data_setUserPreference(1, substr($prefix, 0, 128));

        if ($result) {
            $this->show_form('Sync preferences saved.', true);
        } else {
            $this->show_form('There was a problem saving your sync preferences!');
        }
    }

    function show_form($msg = null, $success = false) {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        $this->show_header('Settings', $msg, $success);

        common_element_start('fb:if-section-not-added', array('section' => 'profile'));
        common_element('h2', null, _('Add an Identi.ca box to my profile'));
        common_element_start('p');
        common_element('fb:add-section-button', array('section' => 'profile'));
        common_element_end('p');

        common_element_end('fb:if-section-not-added');
        common_element_start('p');
        common_element_start('fb:prompt-permission', array('perms' => 'status_update'));
        common_element('h2', null, _('Allow Identi.ca to update my Facebook status'));
        common_element_end('fb:prompt-permission');
        common_element_end('p');

        if ($facebook->api_client->users_hasAppPermission('status_update')) {

            common_element_start('form', array('method' => 'post',
                                               'id' => 'facebook_settings'));

            common_element('h2', null, _('Sync preferences'));

            common_checkbox('noticesync', _('Automatically update my Facebook status with my notices.'),
                                ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND) : true);

            common_checkbox('replysync', _('Send local "@" replies to Facebook.'),
                             ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true);

            // function common_input($id, $label, $value=null,$instructions=null)

            $prefix = $facebook->api_client->data_getUserPreference(1);
            

            common_input('prefix', _('Prefix'),
                         ($prefix) ? $prefix : null,
                         _('A string to prefix notices with.'));
            common_submit('save', _('Save'));

            common_element_end('form');

        }

        $this->show_footer();
    }

}
