<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }


class OStatusInitAction extends Action
{

    var $nickname;
    var $acct;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            $this->clientError(_m('You can use the local subscription!'));
            return false;
        }

        $this->nickname    = $this->trimmed('nickname');
        $this->acct = $this->trimmed('acct');

        return true;
    }
    
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            /* Use a session token for CSRF protection. */
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_m('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }
            $this->ostatusConnect();
        } else {
            $this->showForm();
        }
    }
    
    function showForm($err = null)
    {
        $this->err = $err;
        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            $this->element('title', null, _m('Subscribe to user'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showContent();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    function showContent()
    {
        $this->elementStart('form', array('id' => 'form_ostatus_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('ostatusinit')));
        $this->elementStart('fieldset');
        $this->element('legend', null,  sprintf(_m('Subscribe to %s'), $this->nickname));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'ostatus_nickname'));
        $this->input('nickname', _m('User nickname'), $this->nickname,
                     _m('Nickname of the user you want to follow'));
        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'ostatus_profile'));
        $this->input('acct', _m('Profile Account'), $this->acct,
                     _m('Your account id (i.e. user@identi.ca)'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', _m('Subscribe'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function ostatusConnect()
    {
        $opts = array('allowed_schemes' => array('http', 'https', 'acct'));
        if (Validate::uri($this->acct, $opts)) {
            $bits = parse_url($this->acct);
            if ($bits['scheme'] == 'acct') {
                $this->connectWebfinger($bits['path']);
            } else {
                $this->connectProfile($this->acct);
            }
        } elseif (strpos('@', $this->acct) !== false) {
            $this->connectWebfinger($this->acct);
        }
    }

    function connectWebfinger($acct)
    {
        $w = new Webfinger;

        $result = $w->lookup($acct);
        if (!$result) {
            $this->clientError(_m("Couldn't look up OStatus account profile."));
        }
        foreach ($result->links as $link) {
            if ($link['rel'] == 'http://ostatus.org/schema/1.0/subscribe') {
                // We found a URL - let's redirect!

                $user = User::staticGet('nickname', $this->nickname);
                $target_profile = common_local_url('userbyid', array('id' => $user->id));

                $url = $w->applyTemplate($link['template'], $feed_url);

                common_redirect($url, 303);
            }

        }

    }

    function connectProfile($subscriber_profile)
    {
        $user = User::staticGet('nickname', $this->nickname);
        $target_profile = common_local_url('userbyid', array('id' => $user->id));

        // @fixme hack hack! We should look up the remote sub URL from XRDS
        $suburl = preg_replace('!^(.*)/(.*?)$!', '$1/main/ostatussub', $subscriber_profile);
        $suburl .= '?profile=' . urlencode($target_profile);

        common_redirect($suburl, 303);
    }

    function title()
    {
      return _m('OStatus Connect');  
    }
  
}
