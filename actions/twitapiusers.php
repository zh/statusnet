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
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }
                
		$user = null;
		$email = $this->arg('email');
		$user_id = $this->arg('user_id');

		if ($email) {
			$user = User::staticGet('email', $email);
		} elseif ($user_id) {
		 	$user = $this->get_user($user_id);  
		} elseif (isset($apidata['api_arg'])) {
			$user = $this->get_user($apidata['api_arg']);
	    } elseif (isset($apidata['user'])) {
	        $user = $apidata['user'];
	    }
	
		if (!$user) {		    
			// XXX: Twitter returns a random(?) user instead of throwing and err! -- Zach
			$this->client_error(_('Not found.'), 404, $apidata['content-type']);
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile.'));
			return;
		}

		$twitter_user = $this->twitter_user_array($profile, true);

		// Add in extended user fields offered up by this method
		$twitter_user['created_at'] = $this->date_twitter($profile->created);

		$subbed = DB_DataObject::factory('subscription');
		$subbed->subscriber = $profile->id;
		$subbed_count = (int) $subbed->count() - 1;

		$notices = DB_DataObject::factory('notice');
		$notices->profile_id = $profile->id;
		$notice_count = (int) $notices->count();

		$twitter_user['friends_count'] = (is_int($subbed_count)) ? $subbed_count : 0;
		$twitter_user['statuses_count'] = (is_int($notice_count)) ? $notice_count : 0;

		// Other fields Twitter sends...
		$twitter_user['profile_background_color'] = '';
		$twitter_user['profile_background_image_url'] = '';
		$twitter_user['profile_text_color'] = '';
		$twitter_user['profile_link_color'] = '';
		$twitter_user['profile_sidebar_fill_color'] = '';
        $twitter_user['profile_sidebar_border_color'] = '';
        $twitter_user['profile_background_tile'] = 'false';

		$faves = DB_DataObject::factory('fave');
		$faves->user_id = $user->id;
		$faves_count = (int) $faves->count();
		$twitter_user['favourites_count'] = $faves_count;

		$timezone = 'UTC';

		if ($user->timezone) {
			$timezone = $user->timezone;
		}

		$t = new DateTime;
		$t->setTimezone(new DateTimeZone($timezone));
		$twitter_user['utc_offset'] = $t->format('Z');
		$twitter_user['time_zone'] = $timezone;

		if (isset($apidata['user'])) {

			if ($apidata['user']->isSubscribed($profile)) {
				$twitter_user['following'] = 'true';
			} else {
				$twitter_user['following'] = 'false';
			}
            
            // Notifications on?
		    $sub = Subscription::pkeyGet(array('subscriber' =>
		        $apidata['user']->id, 'subscribed' => $profile->id));
            
            if ($sub) {
                if ($sub->jabber || $sub->sms) {
                    $twitter_user['notifications'] = 'true';
                } else {
                    $twitter_user['notifications'] = 'false';
                }
            }
        }
        
		if ($apidata['content-type'] == 'xml') {
			$this->init_document('xml');
			$this->show_twitter_xml_user($twitter_user);
			$this->end_document('xml');
		} elseif ($apidata['content-type'] == 'json') {
			$this->init_document('json');
			$this->show_json_objects($twitter_user);
			$this->end_document('json');
		} else {
		    
		    // This is in case 'show' was called via /account/verify_credentials
		    // without a format (xml or json).
            header('Content-Type: text/html; charset=utf-8');
            print 'Authorized';
        }

	}
}
