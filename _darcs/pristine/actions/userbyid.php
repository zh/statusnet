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

class UserbyidAction extends Action {
    
    function is_readonly() {                
        return true;
    }
    
    function handle($args) {
        parent::handle($args);
        $id = $this->trimmed('id');
        if (!$id) {
            $this->client_error(_('No id.'));
        }
        $user =& User::staticGet($id);
        if (!$user) {
            $this->client_error(_('No such user.'));
        }

        // support redirecting to FOAF rdf/xml if the agent prefers it
        $page_prefs = 'application/rdf+xml,text/html,application/xhtml+xml,application/xml;q=0.3,text/xml;q=0.2';
        $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : NULL;
        $type = common_negotiate_type(common_accept_to_prefs($httpaccept),
                                      common_accept_to_prefs($page_prefs));
        $page = $type == 'application/rdf+xml' ? 'foaf' : 'showstream';

        $url = common_local_url($page, array('nickname' => $user->nickname));
        common_redirect($url, 303);
    }
}
