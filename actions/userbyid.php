<?php

/**
 * User by ID action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/

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

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * User by ID action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class UserbyidAction extends Action
{
     /**
     * Is read only?
     * 
     * @return boolean true
     */
    function isReadOnly($args)
    {                
        return true;
    }

     /**
     * Class handler.
     * 
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);
        $id = $this->trimmed('id');
        if (!$id) {
            $this->clientError(_('No id.'));
        }
        $user =& User::staticGet($id);
        if (!$user) {
            $this->clientError(_('No such user.'));
        }

        // support redirecting to FOAF rdf/xml if the agent prefers it
        $page_prefs = 'application/rdf+xml,text/html,application/xhtml+xml,application/xml;q=0.3,text/xml;q=0.2';
        $httpaccept = isset($_SERVER['HTTP_ACCEPT'])
                      ? $_SERVER['HTTP_ACCEPT'] : null;
        $type       = common_negotiate_type(common_accept_to_prefs($httpaccept),
                      common_accept_to_prefs($page_prefs));
        $page       = $type == 'application/rdf+xml' ? 'foaf' : 'showstream';
        $url        = common_local_url($page, array('nickname' => $user->nickname));
        common_redirect($url, 303);
    }
}

