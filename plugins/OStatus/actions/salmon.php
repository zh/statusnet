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
    var $user     = null;
    var $xml      = null;
    var $activity = null;

    function prepare($args)
    {
        parent::prepare($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'));
        }

        if ($_SERVER['CONTENT_TYPE'] != 'application/atom+xml') {
            $this->clientError(_('Salmon requires application/atom+xml'));
        }

        $id = $this->trimmed('id');

        if (!$id) {
            $this->clientError(_('No ID.'));
        }

        $this->user = User::staticGet($id);

        if (empty($this->user)) {
            $this->clientError(_('No such user.'));
        }

        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        // XXX: check that document element is Atom entry
        // XXX: check the signature

        $this->act = new Activity($dom->documentElement);

        return true;
    }

    /**
     * Check the posted activity type and break out to appropriate processing.
     */

    function handle($args)
    {
        common_log(LOG_INFO, 'Salmon: incoming post for user '. $this->user->id);

        // TODO : Insert new $xml -> notice code

        if (Event::handle('StartHandleSalmon', array($this->user, $this->activity))) {
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
            case ActivityVerb::FOLLOW:
            case ActivityVerb::FRIEND:
                $this->handleFollow();
                break;
            case ActivityVerb::UNFOLLOW:
                $this->handleUnfollow();
                break;
            }
            Event::handle('EndHandleSalmon', array($this->user, $this->activity));
        }
    }

    /**
     * We've gotten a post event on the Salmon backchannel, probably a reply.
     *
     * @todo validate if we need to handle this post, then call into
     * ostatus_profile's general incoming-post handling.
     */
    function handlePost()
    {
        switch ($this->act->object->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            throw new ClientException("Can't handle that kind of post.");
        }

        // Notice must either be a) in reply to a notice by this user
        // or b) to the attention of this user

        $context = $this->act->context;

        if (!empty($context->replyToID)) {
            $notice = Notice::staticGet('uri', $context->replyToID);
            if (empty($notice)) {
                throw new ClientException("In reply to unknown notice");
            }
            if ($notice->profile_id != $this->user->id) {
                throw new ClientException("In reply to a notice not by this user");
            }
        } else if (!empty($context->attention)) {
            if (!in_array($context->attention, $this->user->uri)) {
                throw new ClientException("To the attention of user(s) not including this one!");
            }
        } else {
            throw new ClientException("Not to anyone in reply to anything!");
        }

        $profile = $this->ensureProfile();
        // @fixme do something with the post
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */

    function handleFollow()
    {
        $oprofile = $this->ensureProfile();
        if ($oprofile) {
            common_log(LOG_INFO, "Setting up subscription from remote {$oprofile->uri} to local {$this->user->nickname}");
            $oprofile->subscribeRemoteToLocal($this->user);
        } else {
            common_log(LOG_INFO, "Can't set up subscription from remote; missing profile.");
        }
    }

    /**
     * We've gotten an unfollow/unsubscribe notification from a remote user.
     * Check if we have a subscription relationship for them and kill it.
     *
     * @fixme probably catch exceptions on fail?
     */
    function handleUnfollow()
    {
        $oprofile = $this->ensureProfile();
        if ($oprofile) {
            common_log(LOG_INFO, "Canceling subscription from remote {$oprofile->uri} to local {$this->user->nickname}");
            Subscription::cancel($oprofile->localProfile(), $this->user->getProfile());
        } else {
            common_log(LOG_ERR, "Can't cancel subscription from remote, didn't find the profile");
        }
    }

    /**
     * Remote user likes one of our posts.
     * Confirm the post is ours, and save a local favorite event.
     */

    function handleFavorite()
    {
        // WORST VARIABLE NAME EVER
        $object = $this->act->object;

        switch ($this->act->object->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            throw new ClientException("Can't handle that kind of object for liking/faving.");
        }

        $notice = Notice::staticGet('uri', $object->id);

        if (empty($notice)) {
            throw new ClientException("Notice with ID $object->id unknown.");
        }

        if ($notice->profile_id != $this->user->id) {
            throw new ClientException("Notice with ID $object->id not posted by $this->user->id.");
        }

        $profile = $this->ensureProfile();

        $old = Fave::pkeyGet(array('user_id' => $profile->id,
                                   'notice_id' => $notice->id));

        if (!empty($old)) {
            throw new ClientException("We already know that's a fave!");
        }

        $fave = new Fave();

        // @fixme need to change this attribute name, maybe references
        $fave->user_id   = $profile->id;
        $fave->notice_id = $notice->id;

        $result = $fave->insert();

        if (!$result) {
            common_log_db_error($fave, 'INSERT', __FILE__);
            throw new ServerException('Could not save new favorite.');
        }
    }

    /**
     * Remote user doesn't like one of our posts after all!
     * Confirm the post is ours, and save a local favorite event.
     */
    function handleUnfavorite()
    {
    }

    /**
     * Hmmmm
     */
    function handleShare()
    {
    }

    /**
     * @return Ostatus_profile
     */
    function ensureProfile()
    {
        $actor = $this->act->actor;
        common_log(LOG_DEBUG, "Received salmon bit: " . var_export($this->act, true));
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
}
