<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapiblocksAction extends TwitterapiAction
{

    function create($args, $apidata)
    {

        parent::handle($args);

        $blockee = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($blockee)) {
            $this->clientError('Not Found', 404, $apidata['content-type']);
            return;
        }

        $user = $apidata['user']; // Always the auth user

        if ($user->hasBlocked($blockee) || $user->block($blockee)) {
            $type = $apidata['content-type'];
            $this->init_document($type);
            $this->show_profile($blockee, $type);
            $this->end_document($type);
        } else {
            $this->serverError(_('Block user failed.'));
        }
    }

    function destroy($args, $apidata)
    {
        parent::handle($args);
        $blockee = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($blockee)) {
            $this->clientError('Not Found', 404, $apidata['content-type']);
            return;
        }

        $user = $apidata['user'];

        if (!$user->hasBlocked($blockee) || $user->unblock($blockee)) {
            $type = $apidata['content-type'];
            $this->init_document($type);
            $this->show_profile($blockee, $type);
            $this->end_document($type);
        } else {
            $this->serverError(_('Unblock user failed.'));
        }
    }
}