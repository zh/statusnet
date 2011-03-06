<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * Peopletag autocomple action.
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
 *
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PeopletagautocompleteAction extends Action
{
    var $user;

    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            $this->clientError(_('Not logged in.'));
            return false;
        }

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
            return false;
        }

        return true;
    }

    /**
     * Handle request
     *
     * Does the subscription and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */

    function handle($args)
    {
        $profile = $this->user->getProfile();
        $tags = $profile->getOwnedTags(common_current_user());

        $tags_array = array();
        while ($tags->fetch()) {
            $arr = array();
            $arr['tag'] = $tags->tag;
            $arr['mode'] = $tags->private ? 'private' : 'public';
            // $arr['url'] = $tags->homeUrl();
            $arr['freq'] = $tags->taggedCount();

            $tags_array[] = $arr;
        }

        $tags->free();

        //common_log(LOG_DEBUG, 'Autocomplete data: ' . json_encode($tags_array));
        print(json_encode($tags_array));
        exit(0);
    }
}
