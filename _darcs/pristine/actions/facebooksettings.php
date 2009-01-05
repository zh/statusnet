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

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, 2); // 2 == Facebook

        $original = clone($flink);
        $flink->set_flags($noticesync, $replysync, false);
        $result = $flink->update($original);

        if ($result) {
            echo '<fb:success message="Sync preferences saved." />';
        }

        $this->show_form();

    }

    function show_form() {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $flink = Foreign_link::getByForeignID($fbuid, 2); // 2 == Facebook

        $this->show_header('Settings');

        $fbml = '<fb:if-section-not-added section="profile">'
            .'<h2>Add an Identi.ca box to my profile</h2>'
            .'<fb:add-section-button section="profile"/>'
            .'</fb:if-section-not-added>';

        $fbml .= '<fb:prompt-permission perms="status_update"><h2>Allow Identi.ca to update my Facebook status</h2></fb:prompt-permission>';

        $fbml .= '<form method="post" id="facebook_settings">'
        .'<h2>Sync preferences</h2>'
        .'<p>';

        if ($flink->noticesync & FOREIGN_NOTICE_SEND) {
            $fbml .= '<input name="noticesync" type="checkbox" class="checkbox" id="noticesync" checked="checked"/>';
        } else {
            $fbml .= '<input name="noticesync" type="checkbox" class="checkbox" id="noticesync">';
        }

        $fbml .= '<label class="checkbox_label" for="noticesync">Automatically update my Facebook status with my notices.</label>'
        .'</p>'
        .'<p>';

        if ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) {
            $fbml .= '<input name="replysync" type="checkbox" class="checkbox" id="replysync" checked="checked"/>';
        } else {
            $fbml .= '<input name="replysync" type="checkbox" class="checkbox" id="replysync"/>';
        }

        $fbml .= '<label class="checkbox_label" for="replysync">Send &quot;@&quot; replies to Facebook.</label>'
        .'</p>'
        .'<p>'
        .'<input type="submit" id="save" name="save" class="submit" value="Save"/>'
        .'</p>'
        .'</form>';

        echo $fbml;

        $this->show_footer();
    }

}
