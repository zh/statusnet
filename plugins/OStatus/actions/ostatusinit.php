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
            $this->clientError(_('You can use the local subscription!'));
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
                $this->showForm(_('There was a problem with your session token. '.
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
      $this->showPage();

    }

    function showContent()
    {
        $this->elementStart('form', array('id' => 'form_ostatus_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('ostatusinit')));
        $this->elementStart('fieldset');
        $this->element('legend', _('Subscribe to a remote user'));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('nickname', _('User nickname'), $this->nickname,
                     _('Nickname of the user you want to follow'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->input('acct', _('Profile Account'), $this->acct,
                     _('Your account id (i.e. user@identi.ca)'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', _('Subscribe'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }        

    function ostatusConnect()
    {
      $w = new Webfinger;

      $result = $w->lookup($this->acct);
      foreach ($result->links as $link) {
          if ($link['rel'] == 'http://ostatus.org/schema/1.0/subscribe') {
              // We found a URL - let's redirect!

              $user = User::staticGet('nickname', $this->nickname);

              $feed_url = common_local_url('ApiTimelineUser',
                                           array('id' => $user->id,
                                                 'format' => 'atom'));
              $url = $w->applyTemplate($link['template'], $feed_url);

              common_redirect($url, 303);
          }

      }
      
    }
    
    function title()
    {
      return _('OStatus Connect');  
    }
  
}