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

require_once(INSTALLDIR.'/lib/facebookutil.php');

class FacebookAction extends Action
{

    function handle($args)
    {
        parent::handle($args);
    }

    function update_profile_box($facebook, $fbuid, $user)
    {

        $notice = $user->getCurrentNotice();

        # Need to include inline CSS for styling the Profile box

         $style = '<style>
         #notices {
         clear: both;
         margin: 0 auto;
         padding: 0;
         list-style-type: none;
         width: 600px;
         border-top: 1px solid #dec5b5;
         }
         #notices a:hover {
         text-decoration: underline;
         }
         .notice_single {
         clear: both;
         display: block;
         margin: 0;
         padding: 5px 5px 5px 0;
         min-height: 48px;
         font-family: Georgia, "Times New Roman", Times, serif;
         font-size: 13px;
         line-height: 16px;
         border-bottom: 1px solid #dec5b5;
         background-color:#FCFFF5;
         opacity:1;
         }
         .notice_single:hover {
         background-color: #f7ebcc;
         }
         .notice_single p {
         display: inline;
         margin: 0;
         padding: 0;
         }
         </style>';

        $html = Facebookaction::render_notice($notice);


        $fbml = "<fb:wide>$style $html</fb:wide>";
        $fbml .= "<fb:narrow>$style $html</fb:narrow>";

        $fbml_main = "<fb:narrow>$style $html</fb:narrow>";

        $facebook->api_client->profile_setFBML(null, $fbuid, $fbml, null, null, $fbml_main);
    }

    # Display methods

    function show_header($selected = 'Home', $msg = null, $success = false)
    {

        start_fbml();

        # Add a timestamp to the CSS file so Facebook cache wont ignore our changes
        $ts = filemtime(theme_file('facebookapp.css'));
        $cssurl = theme_path('facebookapp.css') . "?ts=$ts";

        common_element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => $cssurl));

        common_element('fb:dashboard');

        common_element_start('fb:tabs');
        common_element('fb:tab-item', array('title' => 'Home',
                                            'href' => 'index.php',
                                            'selected' => ($selected == 'Home')));
        common_element('fb:tab-item', array('title' => 'Invite Friends',
                                            'href' => 'invite.php',
                                            'selected' => ($selected == 'Invite')));
        common_element('fb:tab-item', array('title' => 'Settings',
                                            'href' => 'settings.php',
                                            'selected' => ($selected == 'Settings')));
        common_element_end('fb:tabs');


        if ($msg) {
            if ($success) {
                common_element('fb:success', array('message' => $msg));
            } else {
                // XXX do an error message here
            }
        }

        common_element_start('div', 'main_body');

    }

    function show_footer()
    {
        common_element_end('div');
        common_end_xml();
    }

    function showLoginForm($msg = null)
    {
        start_fbml();

        common_element_start('a', array('href' => 'http://identi.ca'));
        common_element('img', array('src' => 'http://theme.identi.ca/identica/logo.png',
                                    'alt' => 'Identi.ca',
                                    'id' => 'logo'));
        common_element_end('a');

        if ($msg) {
             common_element('fb:error', array('message' => $msg));
        }

        common_element("h2", null,
            _('To add the Identi.ca application, you need to log into your Identi.ca account.'));


        common_element_start('div', array('class' => 'instructions'));
        common_element_start('p');
        common_raw('Login with your username and password. Don\'t have a username yet?'
        .' <a href="http://identi.ca/main/register">Register</a> a new account.');
        common_element_end('p');
        common_element_end('div');

        common_element_start('div', array('id' => 'content'));
        common_element_start('form', array('method' => 'post',
                                               'id' => 'login',
                                               'action' => 'index.php'));
        common_input('nickname', _('Nickname'));
        common_password('password', _('Password'));

        common_submit('submit', _('Login'));
        common_element_end('form');

        common_element_start('p');
        common_element('a', array('href' => common_local_url('recoverpassword')),
                       _('Lost or forgotten password?'));
        common_element_end('p');
        common_element_end('div');

        common_end_xml();

    }


}
