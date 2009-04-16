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

class FoafAction extends Action
{
    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');

        if (empty($nickname_arg)) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $this->nickname) {
            common_redirect(common_local_url('foaf',
                                             array('nickname' => $this->nickname)),
                            301);
            return false;
        }

        $this->user = User::staticGet('nickname', $this->nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'), 500);
            return false;
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        header('Content-Type: application/rdf+xml');

        $this->startXML();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:rdfs' =>
                                              'http://www.w3.org/2000/01/rdf-schema#',
                                              'xmlns:geo' =>
                                              'http://www.w3.org/2003/01/geo/wgs84_pos#',
                                              'xmlns' => 'http://xmlns.com/foaf/0.1/'));

        // This is the document about the user

        $this->showPpd('', $this->user->uri);

        // XXX: might not be a person
        $this->elementStart('Person', array('rdf:about' =>
                                             $this->user->uri));
        $this->element('mbox_sha1sum', null, sha1('mailto:' . $this->user->email));
        if ($this->profile->fullname) {
            $this->element('name', null, $this->profile->fullname);
        }
        if ($this->profile->homepage) {
            $this->element('homepage', array('rdf:resource' => $this->profile->homepage));
        }
        if ($this->profile->bio) {
            $this->element('rdfs:comment', null, $this->profile->bio);
        }
        // XXX: more structured location data
        if ($this->profile->location) {
            $this->elementStart('based_near');
            $this->elementStart('geo:SpatialThing');
            $this->element('name', null, $this->profile->location);
            $this->elementEnd('geo:SpatialThing');
            $this->elementEnd('based_near');
        }

        $this->showMicrobloggingAccount($this->profile, common_root_url());

        $avatar = $this->profile->getOriginalAvatar();

        if ($avatar) {
            $this->elementStart('img');
            $this->elementStart('Image', array('rdf:about' => $avatar->url));
            foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
                $scaled = $this->profile->getAvatar($size);
                if (!$scaled->original) { // sometimes the original has one of our scaled sizes
                    $this->elementStart('thumbnail');
                    $this->element('Image', array('rdf:about' => $scaled->url));
                    $this->elementEnd('thumbnail');
                }
            }
            $this->elementEnd('Image');
            $this->elementEnd('img');
        }

        // Get people user is subscribed to

        $person = array();

        $sub = new Subscription();
        $sub->subscriber = $this->profile->id;
        $sub->whereAdd('subscriber != subscribed');

        if ($sub->find()) {
            while ($sub->fetch()) {
                if (!empty($sub->token)) {
                    $other = Remote_profile::staticGet('id', $sub->subscribed);
                } else {
                    $other = User::staticGet('id', $sub->subscribed);
                }
                if (empty($other)) {
                    common_debug('Got a bad subscription: '.print_r($sub,true));
                    continue;
                }
                $this->element('knows', array('rdf:resource' => $other->uri));
                $person[$other->uri] = array(LISTENEE,
                                             $other->id,
                                             $other->nickname,
                                             (empty($sub->token)) ? 'User' : 'Remote_profile');
                $other->free();
                $other = null;
                unset($other);
            }
        }

        $sub->free();
        $sub = null;
        unset($sub);

        // Get people who subscribe to user

        $sub = new Subscription();
        $sub->subscribed = $this->profile->id;
        $sub->whereAdd('subscriber != subscribed');

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->token) {
                    $other = Remote_profile::staticGet('id', $sub->subscriber);
                } else {
                    $other = User::staticGet('id', $sub->subscriber);
                }
                if (!$other) {
                    common_debug('Got a bad subscription: '.print_r($sub,true));
                    continue;
                }
                if (array_key_exists($other->uri, $person)) {
                    $person[$other->uri][0] = BOTH;
                } else {
                    $person[$other->uri] = array(LISTENER,
                                                 $other->id,
                                                 $other->nickname,
                                                 (empty($sub->token)) ? 'User' : 'Remote_profile');
                }
                $other->free();
                $other = null;
                unset($other);
            }
        }

        $sub->free();
        $sub = null;
        unset($sub);

        $this->elementEnd('Person');

        foreach ($person as $uri => $p) {
            $foaf_url = null;
            list($type, $id, $nickname, $cls) = $p;
            if ($cls == 'User') {
                $foaf_url = common_local_url('foaf', array('nickname' => $nickname));
            }
            $profile = Profile::staticGet($id);
            $this->elementStart('Person', array('rdf:about' => $uri));
            if ($type == LISTENER || $type == BOTH) {
                $this->element('knows', array('rdf:resource' => $this->user->uri));
            }
            $this->showMicrobloggingAccount($profile, ($cls == 'User') ?
                                            common_root_url() : null);
            if ($foaf_url) {
                $this->element('rdfs:seeAlso', array('rdf:resource' => $foaf_url));
            }
            $this->elementEnd('Person');
            if ($foaf_url) {
                $this->showPpd($foaf_url, $uri);
            }
            $profile->free();
            $profile = null;
            unset($profile);
        }

        $this->elementEnd('rdf:RDF');
        $this->endXML();
    }

    function showPpd($foaf_url, $person_uri)
    {
        $this->elementStart('PersonalProfileDocument', array('rdf:about' => $foaf_url));
        $this->element('maker', array('rdf:resource' => $person_uri));
        $this->element('primaryTopic', array('rdf:resource' => $person_uri));
        $this->elementEnd('PersonalProfileDocument');
    }

    function showMicrobloggingAccount($profile, $service=null)
    {
        // Their account
        $this->elementStart('holdsAccount');
        $this->elementStart('OnlineAccount');
        if ($service) {
            $this->element('accountServiceHomepage', array('rdf:resource' =>
                                                           $service));
        }
        $this->element('accountName', null, $profile->nickname);
        $this->element('homepage', array('rdf:resource' => $profile->profileurl));
        $this->elementEnd('OnlineAccount');
        $this->elementEnd('holdsAccount');
    }
}
