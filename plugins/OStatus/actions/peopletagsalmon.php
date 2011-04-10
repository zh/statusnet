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
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PeopletagsalmonAction extends SalmonAction
{
    var $peopletag = null;

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            $this->clientError(_('No ID.'));
        }

        $this->peopletag = Profile_list::staticGet('id', $id);

        if (empty($this->peopletag)) {
            $this->clientError(_('No such peopletag.'));
        }

        $oprofile = Ostatus_profile::staticGet('peopletag_id', $id);

        if (!empty($oprofile)) {
            $this->clientError(_m("Can't accept remote posts for a remote peopletag."));
        }

        return true;
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */

    /**
     * Postel's law: consider a "follow" notification as a "join".
     */
    function handleFollow()
    {
        $this->handleSubscribe();
    }

    /**
     * Postel's law: consider an "unfollow" notification as a "unsubscribe".
     */
    function handleUnfollow()
    {
        $this->handleUnsubscribe();
    }

    /**
     * A remote user subscribed.
     * @fixme move permission checks and event call into common code,
     *        currently we're doing the main logic in joingroup action
     *        and so have to repeat it here.
     */

    function handleSubscribe()
    {
        $oprofile = $this->ensureProfile();
        if (!$oprofile) {
            $this->clientError(_m("Can't read profile to set up profiletag subscription."));
        }
        if ($oprofile->isGroup()) {
            $this->clientError(_m("Groups can't subscribe to peopletags."));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} subscribing to local peopletag ".$this->peopletag->getBestName());
        $profile = $oprofile->localProfile();

        if ($this->peopletag->hasSubscriber($profile)) {
            // Already a member; we'll take it silently to aid in resolving
            // inconsistencies on the other side.
            return true;
        }

        // should we block those whom the tagger has blocked from listening to
        // his own updates?

        try {
            Profile_tag_subscription::add($this->peopletag, $profile);
        } catch (Exception $e) {
            $this->serverError(sprintf(_m('Could not subscribe remote user %1$s to peopletag %2$s.'),
                                       $oprofile->uri, $this->peopletag->getBestName()));
        }
    }

    /**
     * A remote user unsubscribed from our peopletag.
     */

    function handleUnsubscribe()
    {
        $oprofile = $this->ensureProfile();
        if (!$oprofile) {
            $this->clientError(_m("Can't read profile to cancel peopletag membership."));
        }
        if ($oprofile->isGroup()) {
            $this->clientError(_m("Groups can't subscribe to peopletags."));
        }

        common_log(LOG_INFO, "Remote profile {$oprofile->uri} unsubscribing from local peopletag ".$this->peopletag->getBestName());
        $profile = $oprofile->localProfile();

        try {
                Profile_tag_subscription::remove($this->peopletag->tagger, $this->peopletag->tag, $profile->id);

        } catch (Exception $e) {
            $this->serverError(sprintf(_m('Could not remove remote user %1$s from peopletag %2$s.'),
                                       $oprofile->uri, $this->peopletag->getBestName()));
            return;
        }
    }
}
