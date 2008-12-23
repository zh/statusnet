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

require_once(INSTALLDIR.'/extlib/facebook/facebook.php');

class FacebookAction extends Action {

    function handle($args) {
        parent::handle($args);
    }

    function get_facebook() {
        $apikey = common_config('facebook', 'apikey');
        $secret = common_config('facebook', 'secret');
        return new Facebook($apikey, $secret);
    }

    function update_profile_box($facebook, $fbuid, $user) {

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

        $html = $this->render_notice($notice);

        $fbml = "<fb:wide>$content $html</fb:wide>";
        $fbml .= "<fb:narrow>$content $html</fb:narrow>";

        $fbml_main = "<fb:narrow>$content $html</fb:narrow>";

        $facebook->api_client->profile_setFBML(null, $fbuid, $fbml, null, null, $fbml_main);
    }

    # Display methods

    function show_header($selected ='Home') {

        # Add a timestamp to the CSS file so Facebook cache wont ignore our changes
        $ts = filemtime(theme_file('facebookapp.css'));
        $cssurl = theme_path('facebookapp.css') . "?ts=$ts";

         $header = '<link rel="stylesheet" type="text/css" href="'. $cssurl . '" />';
         # $header .='<script src="" ></script>';
          $header .= '<fb:dashboard/>';

          $header .=
            '<fb:tabs>'
            .'<fb:tab-item title="Home" href="index.php" selected="' . ($selected == 'Home') .'" />'
            .'<fb:tab-item title="Invite Friends"  href="invite.php" selected="' . ($selected == 'Invite') . '" />'
            .'<fb:tab-item title="Settings"     href="settings.php" selected="' . ($selected == 'Settings') . '" />'
            .'</fb:tabs>';
          $header .= '<div id="main_body">';

      echo $header;

    }

    function show_footer() {
      $footer = '</div>';
      echo $footer;
    }

    function show_login_form() {

        $loginform =
            ' <h2>To add the Identi.ca application, you need to log into your Identi.ca account.</h2>'
            .'<a href="http://identi.ca/">'
            .'    <img src="http://theme.identi.ca/identica/logo.png" alt="Identi.ca" id="logo"/>'
            .'</a>'
            .'<h1 class="pagetitle">Login</h1>'
            .'<div class="instructions">'
            .'    <p>Login with your username and password. Don\'t have a username yet?'
            .'      <a href="http://identi.ca/main/register">Register</a> a new account.'
            .'    </p>'
            .'</div>'
            .'<div id="content">'
            .'    <form method="post" id="login">'
            .'      <p>'
            .'        <label for="nickname">Nickname</label>'
            .'        <input name="nickname" type="text" class="input_text" id="nickname"/>'
            .'      </p>'
            .'      <p>'
            .'          <label for="password">Password</label>'
            .'        <input name="password" type="password" class="password" id="password"/>'
            .'      </p>'
            .'      <p>'
            .'        <input type="submit" id="submit" name="submit" class="submit" value="Login"/>'
            .'      </p>'
            .'    </form>'
            .'    <p>'
            .'      <a href="http://identi.ca/main/recoverpassword">Lost or forgotten password?</a>'
            .'    </p>'
            .'</div';

            echo $loginform;
    }

    function render_notice($notice) {

        global $config;

        $profile = $notice->getProfile();
        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);

        $noticeurl = common_local_url('shownotice', array('notice' => $notice->id));

        # XXX: we need to figure this out better. Is this right?
        if (strcmp($notice->uri, $noticeurl) != 0 && preg_match('/^http/', $notice->uri)) {
            $noticeurl = $notice->uri;
        }

        $html =
        '<li class="notice_single" id="' . $notice->id . '">'
        .'<a href="' . $profile->profileurl . '">'
        .'<img src="';

        if ($avatar) {
            $html .= common_avatar_display_url($avatar);
        } else {
            $html .= common_default_avatar(AVATAR_STREAM_SIZE);
        }

        $html .=
        '" class="avatar stream" width="'
        . AVATAR_STREAM_SIZE . '" height="' . AVATAR_STREAM_SIZE .'"'
        .' alt="';

        if ($profile->fullname) {
            $html .= $profile->fullname;
        } else {
            $html .= $profile->nickname;
        }

        $html .=
        '"></a>'
        .'<a href="' .    $profile->profileurl . '" class="nickname">' . $profile->nickname . '</a>'
        .'<p class="content">' . $notice->rendered . '</p>'
        .'<p class="time">'
        .'<a class="permalink" href="' . $noticeurl . '" title="' . common_exact_date($notice->created) . '">' . common_date_string($notice->created) . '</a>';

        if ($notice->source) {
            $html .= _(' from ');
            $html .= $this->source_link($notice->source);
        }

        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
            $html .=
            ' (<a class="inreplyto" href="' . $replyurl . '">' . _('in reply to...') . ')';
        }

        $html .= '</p></li>';

        return $html;
    }

    function source_link($source) {
        $source_name = _($source);

        $html = '<span class="noticesource">';

        switch ($source) {
         case 'web':
         case 'xmpp':
         case 'mail':
         case 'omb':
         case 'api':
            $html .= $source_name;
            break;
         default:
            $ns = Notice_source::staticGet($source);
            if ($ns) {
                $html .= '<a href="' . $ns->url . '">' . $ns->name . '</a>';
            } else {
                $html .= $source_name;
            }
            break;
        }

        $html .= '</span>';

        return $html;
    }

    function pagination($have_before, $have_after, $page, $fbaction, $args=null) {

        $html = '';

        if ($have_before || $have_after) {
            $html = '<div id="pagination">';
            $html .'<ul id="nav_pagination">';
        }

        if ($have_before) {
            $pargs = array('page' => $page-1);
            $newargs = ($args) ? array_merge($args,$pargs) : $pargs;
            $html .= '<li class="before">';
            $html .'<a href="' . $this->pagination_url($fbaction, $newargs) . '">' . _('« After') . '</a>';
            $html .'</li>';
        }

        if ($have_after) {
            $pargs = array('page' => $page+1);
            $newargs = ($args) ? array_merge($args,$pargs) : $pargs;
            $html .= '<li class="after">';
            $html .'<a href="' . $this->pagination_url($fbaction, $newargs) . '">' . _('Before »') . '</a>';
            $html .'</li>';
        }

        if ($have_before || $have_after) {
            $html .= '<ul>';
            $html .'<div>';
        }
    }

    function pagination_url($fbaction, $args=null) {
        global $config;

        $extra = '';

        if ($args) {
            foreach ($args as $key => $value) {
                $extra .= "&${key}=${value}";
            }
        }

        return "$fbaction?${extra}";
    }

}
