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

        if (empty($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/atom+xml') {
            $this->clientError(_('Salmon requires application/atom+xml'));
        }

        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            common_log(LOG_DEBUG, "Got invalid Salmon post: $xml");
            $this->clientError(_m('Salmon post must be an Atom entry.'));
        }

        // Check the signature
        $salmon = new Salmon;
        if (!common_config('ostatus', 'skip_signatures')) {
            if (!$salmon->verifyMagicEnv($dom)) {
                common_log(LOG_DEBUG, "Salmon signature verification failed.");
                $this->clientError(_m('Salmon signature verification failed.'));
            }
        }

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
            case ActivityVerb::LEAVE:
                $this->handleLeave();
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
     * Hmmmm
     */
    function handleLeave()
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
            common_log(LOG_ERR, "activity with no actor: " . var_export($this->act, true));
            throw new Exception("Received a salmon slap from unidentified actor.");
        }

        return Ostatus_profile::ensureActivityObjectProfile($actor);
    }

    function saveNotice()
    {
        $oprofile = $this->ensureProfile();
        return $oprofile->processPost($this->act, 'salmon');
    }
}
