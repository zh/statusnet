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

class FacebookinviteAction extends FacebookAction
{

    function handle($args)
    {
        parent::handle($args);

        if ($this->arg('ids')) {
            $this->showThankYou();
        } else {
            $this->showInviteForm();
        }
    }


    function showThankYou()
    {
        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $this->showHeader('Invite');

        common_element('h2', null, _('Thanks for inviting your friends to use Identi.ca!'));
        common_element('p', null, _('Invitations have been sent to the following users:'));

        $friend_ids = $_POST['ids']; // Hmm... $this->arg('ids') doesn't seem to work

        common_element_start("ul");

        foreach ($friend_ids as $friend) {
            common_element_start('li');
            common_element('fb:profile-pic', array('uid' => $friend));
            common_element('fb:name', array('uid' => $friend,
                                            'capitalize' => 'true'));
            common_element_end('li');
        }

        common_element_end("ul");

        $this->showFooter();
    }

    function showInviteForm()
    {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $this->showHeader();
        $this->showNav('Invite');

        // Get a list of users who are already using the app for exclusion
        $exclude_ids = $facebook->api_client->friends_getAppUsers();

        $content = _('You have been invited to Identi.ca!') .
            htmlentities('<fb:req-choice url="http://apps.facebook.com/identica_app/" label="Add"/>');

        common_element_start('fb:request-form', array('action' => 'invite.php',
                                                      'method' => 'post',
                                                      'invite' => 'true',
                                                      'type' => 'Identi.ca',
                                                      'content' => $content));
        common_hidden('invite', 'true');
        $actiontext = 'Invite your friends to use Identi.ca.';
        common_element('fb:multi-friend-selector', array('showborder' => 'false',
                                                               'actiontext' => $actiontext,
                                                               'exclude_ids' => implode(',', $exclude_ids),
                                                               'bypass' => 'cancel'));

        common_element_end('fb:request-form');

        common_element('h2', null, _('Friends already using Identi.ca:'));
        common_element_start("ul");

        foreach ($exclude_ids as $friend) {
            common_element_start('li');
            common_element('fb:profile-pic', array('uid' => $friend));
            common_element('fb:name', array('uid' => $friend,
                                            'capitalize' => 'true'));
            common_element_end('li');
        }

        common_element_end("ul");

        $this->showFooter();

    }

}
