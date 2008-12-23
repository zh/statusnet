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

class SettingsAction extends Action {

    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            common_user_error(_('Not logged in.'));
            return;
        } else if (!common_is_real_login()) {
            # Cookie theft means that automatic logins can't
            # change important settings or see private info, and
            # _all_ our settings are important
            common_set_returnto($this->self_url());
            common_redirect(common_local_url('login'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handle_post();
        } else {
            $this->show_form();
        }
    }

    # override!
    function handle_post()
    {
        return false;
    }

    function show_form($msg=null, $success=false)
    {
        return false;
    }

    function message($msg, $success)
    {
        if ($msg) {
            common_element('div', ($success) ? 'success' : 'error',
                           $msg);
        }
    }

    function form_header($title, $msg=null, $success=false)
    {
        common_show_header($title,
                           null,
                           array($msg, $success),
                           array($this, 'show_top'));
    }

    function show_top($arr)
    {
        $msg = $arr[0];
        $success = $arr[1];
        if ($msg) {
            $this->message($msg, $success);
        } else {
            $inst = $this->get_instructions();
            $output = common_markup_to_html($inst);
            common_element_start('div', 'instructions');
            common_raw($output);
            common_element_end('div');
        }
        $this->settings_menu();
    }

    function settings_menu()
    {
        # action => array('prompt', 'title')
        $menu =
          array('profilesettings' =>
                array(_('Profile'),
                      _('Change your profile settings')),
                'emailsettings' =>
                array(_('Email'),
                      _('Change email handling')),
                'openidsettings' =>
                array(_('OpenID'),
                      _('Add or remove OpenIDs')),
                'smssettings' =>
                array(_('SMS'),
                      _('Updates by SMS')),
                'imsettings' =>
                array(_('IM'),
                      _('Updates by instant messenger (IM)')),
                'twittersettings' =>
                array(_('Twitter'),
                      _('Twitter integration options')),
                'othersettings' =>
                array(_('Other'),
                      _('Other options')));
        
        $action = $this->trimmed('action');
        common_element_start('ul', array('id' => 'nav_views'));
        foreach ($menu as $menuaction => $menudesc) {
            if ($menuaction == 'imsettings' &&
                !common_config('xmpp', 'enabled')) {
                continue;
            }
            common_menu_item(common_local_url($menuaction),
                    $menudesc[0],
                    $menudesc[1],
                    $action == $menuaction);
        }
        common_element_end('ul');
    }
}
