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

define('LISTENER', 1);
define('LISTENEE', -1);
define('BOTH', 0);

class FoafAction extends Action {

	function handle($args) {
		parent::handle($args);

		$nickname = $this->trimmed('nickname');

		$user = User::staticGet('nickname', $nickname);

		if (!$user) {
			common_user_error(_('No such user'), 404);
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile'), 500);
			return;
		}

		header('Content-Type: application/rdf+xml');

		common_start_xml();
		common_element_start('rdf:RDF', array('xmlns:rdf' =>
											  'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
											  'xmlns:rdfs' =>
											  'http://www.w3.org/2000/01/rdf-schema#',
											  'xmlns:geo' =>
											  'http://www.w3.org/2003/01/geo/wgs84_pos#',
											  'xmlns' => 'http://xmlns.com/foaf/0.1/'));

		# This is the document about the user

		$this->show_ppd('', $user->uri);

		# XXX: might not be a person
		common_element_start('Person', array('rdf:about' =>
											 $user->uri));
		common_element('mbox_sha1sum', NULL, sha1('mailto:' . $user->email));
		if ($profile->fullname) {
			common_element('name', NULL, $profile->fullname);
		}
		if ($profile->homepage) {
			common_element('homepage', array('rdf:resource' => $profile->homepage));
		}
		if ($profile->bio) {
			common_element('rdfs:comment', NULL, $profile->bio);
		}
		# XXX: more structured location data
		if ($profile->location) {
			common_element_start('based_near');
			common_element_start('geo:SpatialThing');
			common_element('name', NULL, $profile->location);
			common_element_end('geo:SpatialThing');
			common_element_end('based_near');
		}

		$this->show_microblogging_account($profile, common_root_url());

		$avatar = $profile->getOriginalAvatar();

		if ($avatar) {
			common_element_start('img');
			common_element_start('Image', array('rdf:about' => $avatar->url));
			foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
				$scaled = $profile->getAvatar($size);
				if (!$scaled->original) { # sometimes the original has one of our scaled sizes
					common_element_start('thumbnail');
					common_element('Image', array('rdf:about' => $scaled->url));
					common_element_end('thumbnail');
				}
			}
			common_element_end('Image');
			common_element_end('img');
		}

		# Get people user is subscribed to

		$person = array();

		$sub = new Subscription();
		$sub->subscriber = $profile->id;

		if ($sub->find()) {
			while ($sub->fetch()) {
				if ($sub->token) {
					$other = Remote_profile::staticGet('id', $sub->subscribed);
				} else {
					$other = User::staticGet('id', $sub->subscribed);
				}
				if (!$other) {
					common_debug('Got a bad subscription: '.print_r($sub,TRUE));
					continue;
				}
				common_element('knows', array('rdf:resource' => $other->uri));
				$person[$other->uri] = array(LISTENEE, $other);
			}
		}

		# Get people who subscribe to user

		$sub = new Subscription();
		$sub->subscribed = $profile->id;

		if ($sub->find()) {
			while ($sub->fetch()) {
				if ($sub->token) {
					$other = Remote_profile::staticGet('id', $sub->subscriber);
				} else {
					$other = User::staticGet('id', $sub->subscriber);
				}
				if (!$other) {
					common_debug('Got a bad subscription: '.print_r($sub,TRUE));
					continue;
				}
				if (array_key_exists($other->uri, $person)) {
					$person[$other->uri][0] = BOTH;
				} else {
					$person[$other->uri] = array(LISTENER, $other);
				}
			}
		}

		common_element_end('Person');

		foreach ($person as $uri => $p) {
			$foaf_url = NULL;
			if ($p[1] instanceof User) {
				$foaf_url = common_local_url('foaf', array('nickname' => $p[1]->nickname));
			}
			$profile = Profile::staticGet($p[1]->id);
			common_element_start('Person', array('rdf:about' => $uri));
			if ($p[0] == LISTENER || $p[0] == BOTH) {
				common_element('knows', array('rdf:resource' => $user->uri));
			}
			$this->show_microblogging_account($profile, ($p[1] instanceof User) ?
											  common_root_url() : NULL);
			if ($foaf_url) {
				common_element('rdfs:seeAlso', array('rdf:resource' => $foaf_url));
			}
			common_element_end('Person');
			if ($foaf_url) {
				$this->show_ppd($foaf_url, $uri);
			}
		}

		common_element_end('rdf:RDF');
	}

	function show_ppd($foaf_url, $person_uri) {
		common_element_start('PersonalProfileDocument', array('rdf:about' => $foaf_url));
		common_element('maker', array('rdf:resource' => $person_uri));
		common_element('primaryTopic', array('rdf:resource' => $person_uri));
		common_element_end('PersonalProfileDocument');
	}

	function show_microblogging_account($profile, $service=NULL) {
		# Their account
		common_element_start('holdsAccount');
		common_element_start('OnlineAccount');
		if ($service) {
			common_element('accountServiceHomepage', array('rdf:resource' =>
														   $service));
		}
		common_element('accountName', NULL, $profile->nickname);
		common_element('homepage', array('rdf:resource' => $profile->profileurl));
		common_element_end('OnlineAccount');
		common_element_end('holdsAccount');
	}
}
