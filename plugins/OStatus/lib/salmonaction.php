<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class SalmonAction extends Action
{
    var $xml      = null;
    var $activity = null;

    function prepare($args)
    {
        StatusNet::setApi(true); // Send smaller error pages

        parent::prepare($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'));
        }

        if ($_SERVER['CONTENT_TYPE'] != 'application/atom+xml') {
            $this->clientError(_('Salmon requires application/atom+xml'));
        }

        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            $this->clientError(_m('Salmon post must be an Atom entry.'));
        }
        // XXX: check the signature

        $this->act = new Activity($dom->documentElement);
        return true;
    }

    /**
     * Check the posted activity type and break out to appropriate processing.
     */

    function handle($args)
    {
        StatusNet::setApi(true); // Send smaller error pages

        // TODO : Insert new $xml -> notice code

        if (Event::handle('StartHandleSalmon', array($this->activity))) {
            switch ($this->act->verb)
            {
            case ActivityVerb::POST:
                $this->handlePost();
                break;
            case ActivityVerb::SHARE:
                $this->handleShare();
                break;
            case ActivityVerb::FAVORITE:
                $this->handleFavorite();
                break;
            case ActivityVerb::UNFAVORITE:
                $this->handleUnfavorite();
                break;
            case ActivityVerb::FOLLOW:
            case ActivityVerb::FRIEND:
                $this->handleFollow();
                break;
            case ActivityVerb::UNFOLLOW:
                $this->handleUnfollow();
                break;
            case ActivityVerb::JOIN:
                $this->handleJoin();
                break;
            default:
                throw new ClientException(_("Unimplemented."));
            }
            Event::handle('EndHandleSalmon', array($this->activity));
        }
    }

    function handlePost()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    function handleFollow()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    function handleUnfollow()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    function handleFavorite()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    /**
     * Remote user doesn't like one of our posts after all!
     * Confirm the post is ours, and delete a local favorite event.
     */

    function handleUnfavorite()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    /**
     * Hmmmm
     */
    function handleShare()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    /**
     * Hmmmm
     */
    function handleJoin()
    {
        throw new ClientException(_("Unimplemented!"));
    }

    /**
     * @return Ostatus_profile
     */
    function ensureProfile()
    {
        $actor = $this->act->actor;
        if (empty($actor->id)) {
            common_log(LOG_ERR, "broken actor: " . var_export($actor, true));
            throw new Exception("Received a salmon slap from unidentified actor.");
        }

        return Ostatus_profile::ensureActorProfile($this->act);
    }

    /**
     * @fixme merge into Ostatus_profile::ensureActorProfile and friends
     */
    function createProfile()
    {
        $actor = $this->act->actor;

        $profile = new Profile();

        $profile->nickname = $this->nicknameFromURI($actor->id);

        if (empty($profile->nickname)) {
            $profile->nickname = common_nicknamize($actor->title);
        }

        $profile->fullname   = $actor->title;
        $profile->bio        = $actor->summary; // XXX: is that right?
        $profile->profileurl = $actor->link; // XXX: is that right?
        $profile->created    = common_sql_now();

        $id = $profile->insert();

        if (empty($id)) {
            common_log_db_error($profile, 'INSERT', __FILE__);
            throw new Exception("Couldn't save new profile for $actor->id\n");
        }

        // XXX: add avatars

        $op = new Ostatus_profile();

        $op->profile_id = $id;
        $op->homeuri    = $actor->id;
        $op->created    = $profile->created;

        // XXX: determine feed URI from source or Webfinger or whatever

        $id = $op->insert();

        if (empty($id)) {
            common_log_db_error($op, 'INSERT', __FILE__);
            throw new Exception("Couldn't save new ostatus profile for $actor->id\n");
        }

        return $profile;
    }

    /**
     * @fixme should be merged into Ostatus_profile
     */
    function nicknameFromURI($uri)
    {
        preg_match('/(\w+):/', $uri, $matches);

        $protocol = $matches[1];

        switch ($protocol) {
        case 'acct':
        case 'mailto':
            if (preg_match("/^$protocol:(.*)?@.*\$/", $uri, $matches)) {
                return common_canonical_nickname($matches[1]);
            }
            return null;
        case 'http':
            return common_url_to_nickname($uri);
            break;
        default:
            return null;
        }
    }

    function saveNotice()
    {
        $oprofile = $this->ensureProfile();

        // Get (safe!) HTML and text versions of the content

        require_once(INSTALLDIR.'/extlib/HTMLPurifier/HTMLPurifier.auto.php');

        $html = $this->act->object->content;

        $rendered = HTMLPurifier::purify($html);
        $content = html_entity_decode(strip_tags($rendered));

        $options = array('is_local' => Notice::REMOTE_OMB,
                         'uri' => $this->act->object->id,
                         'url' => $this->act->object->link,
                         'rendered' => $rendered);

        if (!empty($this->act->context->location)) {
            $options['lat'] = $location->lat;
            $options['lon'] = $location->lon;
            if ($location->location_id) {
                $options['location_ns'] = $location->location_ns;
                $options['location_id'] = $location->location_id;
            }
        }

        if (!empty($this->act->context->replyToID)) {
            $orig = Notice::staticGet('uri',
                                      $this->act->context->replyToID);
            if (!empty($orig)) {
                $options['reply_to'] = $orig->id;
            }
        }

        if (!empty($this->act->time)) {
            $options['created'] = common_sql_time($this->act->time);
        }

        return Notice::saveNew($oprofile->profile_id,
                               $content,
                               'ostatus+salmon',
                               $options);
    }
}
