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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapiaccountAction extends TwitterapiAction
{
    function verify_credentials($args, $apidata)
    {
        parent::handle($args);

        switch ($apidata['content-type']) {
        case 'xml':
        case 'json':
            $action_obj = new TwitapiusersAction();
            $action_obj->prepare($args);
            call_user_func(array($action_obj, 'show'), $args, $apidata);
            break;
        default:
            header('Content-Type: text/html; charset=utf-8');
            print 'Authorized';
        }
    }

   function end_session($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), $code=501);
    }

    function update_location($args, $apidata)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'), 400, $apidata['content-type']);
            return;
        }

        $location = trim($this->arg('location'));

        if (!is_null($location) && mb_strlen($location) > 255) {

            // XXX: But Twitter just truncates and runs with it. -- Zach
            $this->clientError(_('That\'s too long. Max notice size is 255 chars.'), 406, $apidate['content-type']);
            return;
        }

        $user = $apidata['user'];
        $profile = $user->getProfile();

        if (!$profile) {
            $this->serverError(_('User has no profile.'));
            return;
        }

        $orig_profile = clone($profile);
        $profile->location = $location;

        $result = $profile->update($orig_profile);

        if (!$result) {
            common_log_db_error($profile, 'UPDATE', __FILE__);
            $this->serverError(_('Couldn\'t save profile.'));
            return;
        }

        common_broadcast_profile($profile);
        $type = $apidata['content-type'];

        $this->init_document($type);
        $this->show_profile($profile, $type);
        $this->end_document($type);
    }


    function update_delivery_device($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), $code=501);
    }

    // We don't have a rate limit, but some clients check this method.
    // It always returns the same thing: 100 hit left.
    function rate_limit_status($args, $apidata)
    {
        parent::handle($args);

        $type = $apidata['content-type'];
        $this->init_document($type);

        if ($apidata['content-type'] == 'xml') {
            $this->elementStart('hash');
            $this->element('remaining-hits', array('type' => 'integer'), 100);
            $this->element('hourly-limit', array('type' => 'integer'), 100);
            $this->element('reset-time', array('type' => 'datetime'), null);
            $this->element('reset_time_in_seconds', array('type' => 'integer'), 0);
            $this->elementEnd('hash');
        } elseif ($apidata['content-type'] == 'json') {

            $out = array('reset_time_in_seconds' => 0,
                         'remaining_hits' => 100,
                         'hourly_limit' => 100,
                         'reset_time' => '');
            print json_encode($out);
        }

        $this->end_document($type);
    }
}
