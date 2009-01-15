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

require_once(INSTALLDIR.'/lib/omb.php');

class RequesttokenAction extends Action
{
    
    function is_readonly()
    {
        return false;
    }
    
    function handle($args)
    {
        parent::handle($args);
        try {
            common_remove_magic_from_request();
            $req = OAuthRequest::from_request();
            $server = omb_oauth_server();
            $token = $server->fetch_request_token($req);
            print $token;
        } catch (OAuthException $e) {
            $this->serverError($e->getMessage());
        }
    }
}
