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

        $this->display();
    }

    function display()
    {

        $facebook = get_facebook();

        $fbuid = $facebook->require_login();

        $this->show_header('Invite');

        $fbml = '<fb:request-form '
        .'action="invite.php" '
        .'method="POST" '
        .'invite="true" '
        .'type="Identi.ca" '
        .'content="You have been invited to Identi.ca! '
        . htmlentities('<fb:req-choice url="http://apps.facebook.com/identica_app/" label="Add"/>') . '">'
        .'<fb:multi-friend-selector showborder="false" actiontext="Invite your friends to use Identi.ca.">'
        .'</fb:request-form>';

        echo $fbml;

        $this->show_footer();

    }

}
