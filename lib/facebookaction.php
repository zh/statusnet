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

        $this->elementStart('a', array('class' => 'url home bookmark',
                                            'href' => common_local_url('public')));
        if (common_config('site', 'logo') || file_exists(theme_file('logo.png'))) {
            $this->element('img', array('class' => 'logo photo',
                'src' => (common_config('site', 'logo')) ?
                    common_config('site', 'logo') : theme_path('logo.png'),
                'alt' => common_config('site', 'name')));
        }

        $this->element('span', array('class' => 'fn org'), common_config('site', 'name'));
        $this->elementEnd('a');

    }

    function showHeader($msg = null, $success = false)
    {
        startFBML();

        $this->elementStart('fb:if-section-not-added', array('section' => 'profile'));
        $this->elementStart('span', array('id' => 'add_to_profile'));
        $this->element('fb:add-section-button', array('section' => 'profile'));
        $this->elementEnd('span');
        $this->elementEnd('fb:if-section-not-added');

        $this->showLogo();

        if ($msg) {
            if ($success) {
                $this->element('fb:success', array('message' => $msg));
            } else {
                // XXX do an error message here
            }
        }

        $this->elementStart('div', 'main_body');

    }

    function showNav($selected = 'Home')
    {

        $this->elementStart('dl', array("id" => 'site_nav_local_views'));
        $this->element('dt', null, _('Local Views'));
        $this->elementStart('dd');

        $this->elementStart('ul', array('class' => 'nav'));

        $this->elementStart('li', array('class' =>
            ($selected == 'Home') ? 'current' : 'facebook_home'));
        $this->element('a',
            array('href' => 'index.php', 'title' => _('Home')), _('Home'));
        $this->elementEnd('li');

        $this->elementStart('li',
            array('class' =>
                ($selected == 'Invite') ? 'current' : 'facebook_invite'));
        $this->element('a',
            array('href' => 'invite.php', 'title' => _('Invite')), _('Invite'));
        $this->elementEnd('li');

        $this->elementStart('li',
            array('class' =>
                ($selected == 'Settings') ? 'current' : 'facebook_settings'));
        $this->element('a',
            array('href' => 'settings.php',
                'title' => _('Settings')), _('Settings'));
        $this->elementEnd('li');

        $this->elementEnd('ul');

        $this->elementEnd('dd');
        $this->elementEnd('dl');

    }

    function showFooter()
    {
        $this->elementEnd('div');
        $this->endXml();
    }

    function showInstructions()
    {
        global $xw;

        $this->elementStart('dl', array('class' => 'system_notice'));
        $this->element('dt', null, 'Page Notice');

        $loginmsg_part1 = _('To use the %s Facebook Application you need to login ' .
            'with your username and password. Don\'t have a username yet? ');

        $loginmsg_part2 = _(' a new account.');

        $this->elementStart('dd');
        $this->elementStart('p');
        $this->text(sprintf($loginmsg_part1, common_config('site', 'name')));
        $this->element('a',
            array('href' => common_local_url('register')), _('Register'));
        $this->text($loginmsg_part2);
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }

    function showStylesheets()
    {
        global $xw;

        $this->element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => getFacebookBaseCSS()));

        $this->element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => getFacebookThemeCSS()));
    }

    function showScripts()
    {
        global $xw;

        $this->element('script', array('type' => 'text/javascript',
                                       'src' => getFacebookJS()));

    }

    function showLoginForm($msg = null)
    {
        startFBML();

        $this->showStylesheets();
        $this->showScripts();

        $this->showLogo();

        $this->elementStart('div', array('class' => 'content'));
        $this->element('h1', null, _('Login'));

        if ($msg) {
             $this->element('fb:error', array('message' => $msg));
        }

        $this->showInstructions();

        $this->elementStart('div', array('id' => 'content_inner'));

        $this->elementStart('form', array('method' => 'post',
                                               'class' => 'form_settings',
                                               'id' => 'login',
                                               'action' => 'index.php'));

        $this->elementStart('fieldset');
        $this->element('legend', null, _('Login to site'));

        $this->elementStart('ul', array('class' => 'form_datas'));
        $this->elementStart('li');
        $this->input('nickname', _('Nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->password('password', _('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->submit('submit', _('Login'));
        $this->elementEnd('form');

        $this->elementStart('p');
        $this->element('a', array('href' => common_local_url('recoverpassword')),
                       _('Lost or forgotten password?'));
        $this->elementEnd('p');

        $this->elementEnd('div');

        $this->endXml();

    }

    function showNoticeForm($user)
    {

        global $xw;

        $this->elementStart('form', array('id' => 'form_notice',
                                           'method' => 'post',
                                           'action' => 'index.php'));

        $this->elementStart('fieldset');
        $this->element('legend', null, 'Send a notice');

        $this->elementStart('ul', 'form_datas');
        $this->elementStart('li', array('id' => 'noticcommon_elemente_text'));
        $this->element('label', array('for' => 'notice_data-text'),
                            sprintf(_('What\'s up, %s?'), $user->nickname));

        $this->element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'status_textarea'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementStart('dl', 'form_note');
        $this->element('dt', null, _('Available characters'));
        $this->element('dd', array('id' => 'notice_text-count'),
                            '140');
        $this->elementEnd('dl');

        $this->elementStart('ul', array('class' => 'form_actions'));

        $this->elementStart('li', array('id' => 'notice_submit'));

        $this->submit('submit', _('Send'));

        /*
        $this->element('input', array('id' => 'notice_action-submit',
                                           'class' => 'submit',
                                           'name' => 'status_submit',
                                           'type' => 'submit',
                                           'value' => _('Send')));
        */
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

}
