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

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/facebookutil.php';

class FacebookAction extends Action
{

    function handle($args)
    {
        parent::handle($args);
    }

    function showLogo(){

        global $xw;

        $this->showStylesheets();
        $this->showScripts();

        common_element_start('a', array('class' => 'url home bookmark',
                                            'href' => common_local_url('public')));
        if (common_config('site', 'logo') || file_exists(theme_file('logo.png'))) {
            common_element('img', array('class' => 'logo photo',
                'src' => (common_config('site', 'logo')) ?
                    common_config('site', 'logo') : theme_path('logo.png'),
                'alt' => common_config('site', 'name')));
        }

        common_element('span', array('class' => 'fn org'), common_config('site', 'name'));
        common_element_end('a');

    }


    function showHeader($msg = null, $success = false) 
    {
        startFBML();

        common_element_start('fb:if-section-not-added', array('section' => 'profile'));
        common_element_start('span', array('id' => 'add_to_profile'));
        common_element('fb:add-section-button', array('section' => 'profile'));
        common_element_end('span');
        common_element_end('fb:if-section-not-added');
        
        $this->showLogo();

        if ($msg) {
            if ($success) {
                common_element('fb:success', array('message' => $msg));
            } else {
                // XXX do an error message here
            }
        }

        common_element_start('div', 'main_body');        
        
    }

    function showNav($selected = 'Home')
    {

        common_element_start('dl', array("id" => 'site_nav_local_views'));
        common_element('dt', null, _('Local Views'));
        common_element_start('dd');

        common_element_start('ul', array('class' => 'nav'));

        common_element_start('li', array('class' =>
            ($selected == 'Home') ? 'current' : 'facebook_home'));
        common_element('a',
            array('href' => 'index.php', 'title' => _('Home')), _('Home'));
        common_element_end('li');

        common_element_start('li',
            array('class' =>
                ($selected == 'Invite') ? 'current' : 'facebook_invite'));
        common_element('a',
            array('href' => 'invite.php', 'title' => _('Invite')), _('Invite'));
        common_element_end('li');

        common_element_start('li',
            array('class' =>
                ($selected == 'Settings') ? 'current' : 'facebook_settings'));
        common_element('a',
            array('href' => 'settings.php',
                'title' => _('Settings')), _('Settings'));
        common_element_end('li');

        common_element_end('ul');

        common_element_end('dd');
        common_element_end('dl');

    }

    function showFooter()
    {
        common_element_end('div');
        common_end_xml();
    }


    function showInstructions()
    {
        global $xw;

        common_element_start('dl', array('class' => 'system_notice'));
        common_element('dt', null, 'Page Notice');

        $loginmsg_part1 = _('To use the %s Facebook Application you need to login ' .
            'with your username and password. Don\'t have a username yet? ');

        $loginmsg_part2 = _(' a new account.');

        common_element_start('dd');
        common_element_start('p');
        common_text(sprintf($loginmsg_part1, common_config('site', 'name')));
        common_element('a',
            array('href' => common_local_url('register')), _('Register'));
        common_text($loginmsg_part2);
        common_element_end('dd');
        common_element_end('dl');
    }


    function showStylesheets() 
    {
        global $xw;
        
        common_element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => getFacebookBaseCSS()));

        common_element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => getFacebookThemeCSS()));
    }
    
    function showScripts() 
    {
        global $xw;
        
        common_element('script', array('type' => 'text/javascript',
                                       'src' => getFacebookJS()));
        
    }

    function showLoginForm($msg = null)
    {
        startFBML();

        $this->showStylesheets();
        $this->showScripts();

        $this->showLogo();

        common_element_start('div', array('class' => 'content'));
        common_element('h1', null, _('Login'));

        if ($msg) {
             common_element('fb:error', array('message' => $msg));
        }

        $this->showInstructions();

        common_element_start('div', array('id' => 'content_inner'));

        common_element_start('form', array('method' => 'post',
                                               'class' => 'form_settings',
                                               'id' => 'login',
                                               'action' => 'index.php'));

        common_element_start('fieldset');
        common_element('legend', null, _('Login to site'));

        common_element_start('ul', array('class' => 'form_datas'));
        common_element_start('li');
        common_input('nickname', _('Nickname'));
        common_element_end('li');
        common_element_start('li');
        common_password('password', _('Password'));
        common_element_end('li');
        common_element_end('ul');

        common_submit('submit', _('Login'));
        common_element_end('form');

        common_element_start('p');
        common_element('a', array('href' => common_local_url('recoverpassword')),
                       _('Lost or forgotten password?'));
        common_element_end('p');

        common_element_end('div');

        common_end_xml();

    }


    function showNoticeForm($user) 
    {
    
        global $xw;

        common_element_start('form', array('id' => 'form_notice',
                                           'method' => 'post',
                                           'action' => 'index.php'));

        common_element_start('fieldset');
        common_element('legend', null, 'Send a notice');

        common_element_start('ul', 'form_datas');
        common_element_start('li', array('id' => 'noticcommon_elemente_text'));
        common_element('label', array('for' => 'notice_data-text'),
                            sprintf(_('What\'s up, %s?'), $user->nickname));

        common_element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'status_textarea'));
        common_element_end('li');
        common_element_end('ul');

        common_element_start('dl', 'form_note');
        common_element('dt', null, _('Available characters'));
        common_element('dd', array('id' => 'notice_text-count'),
                            '140');
        common_element_end('dl');

        common_element_start('ul', array('class' => 'form_actions'));

        common_element_start('li', array('id' => 'notice_submit'));
        
        common_submit('submit', _('Send'));
        
        /*
        common_element('input', array('id' => 'notice_action-submit',
                                           'class' => 'submit',
                                           'name' => 'status_submit',
                                           'type' => 'submit',
                                           'value' => _('Send')));
        */
        common_element_end('li');
        common_element_end('ul');    
        common_element_end('fieldset');    
        common_element_end('form');
    }

}
