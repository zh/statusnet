<?php
/**
 * Handle an updateprofile action
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

require_once INSTALLDIR.'/lib/omb.php';
require_once INSTALLDIR.'/extlib/libomb/service_provider.php';

/**
 * Handle an updateprofile action
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class UpdateprofileAction extends Action
{

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        $version = $req->get_parameter('omb_version');
        if ($version != OMB_VERSION_01) {
            $this->clientError(_('Unsupported OMB version'), 400);
            return false;
        }
        # First, check to see if listenee exists
        $listenee =  $req->get_parameter('omb_listenee');
        $remote = Remote_profile::staticGet('uri', $listenee);
        if (!$remote) {
            $this->clientError(_('Profile unknown'), 404);
            return false;
        }
        # Second, check to see if they should be able to post updates!
        # We see if there are any subscriptions to that remote user with
        # the given token.

        $sub = new Subscription();
        $sub->subscribed = $remote->id;
        $sub->token = $token->key;
        if (!$sub->find(true)) {
            $this->clientError(_('You did not send us that profile'), 403);
            return false;
        }

        $profile = Profile::staticGet('id', $remote->id);
        if (!$profile) {
            # This one is our fault
            $this->serverError(_('Remote profile with no matching profile'), 500);
            return false;
        }
        $nickname = $req->get_parameter('omb_listenee_nickname');
        if ($nickname && !Validate::string($nickname, array('min_length' => 1,
                                                            'max_length' => 64,
                                                            'format' => NICKNAME_FMT))) {
            $this->clientError(_('Nickname must have only lowercase letters and numbers and no spaces.'));
            return false;
        }
        $license = $req->get_parameter('omb_listenee_license');
        if ($license && !common_valid_http_url($license)) {
            $this->clientError(sprintf(_("Invalid license URL '%s'"), $license));
            return false;
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        try {
            $srv = new OMB_Service_Provider(null, omb_oauth_datastore(),
                                            omb_oauth_server());
            $srv->handleUpdateProfile();
        } catch (Exception $e) {
            $this->serverError($e->getMessage());
            return;
        }
    }
}