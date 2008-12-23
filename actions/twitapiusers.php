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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapiusersAction extends TwitterapiAction
{

    function show($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            common_user_error(_('API method not found!'), $code = 404);
            return;
        }

        $user = null;
        $email = $this->arg('email');

        if ($email) {
            $user = User::staticGet('email', $email);
        } elseif (isset($apidata['api_arg'])) {
            $user = $this->get_user($apidata['api_arg']);
        }

        if (!$user) {
            // XXX: Twitter returns a random(?) user instead of throwing and err! -- Zach
            $this->client_error(_('Not found.'), 404, $apidata['content-type']);
            return;
        }

        $this->show_extended_profile($user, $apidata);
    }

}
