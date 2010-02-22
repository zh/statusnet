<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class FeedSubException extends Exception
{
}

class OStatusPlugin extends Plugin
{
    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        // Discovery actions
        $m->connect('.well-known/host-meta',
                    array('action' => 'hostmeta'));
        $m->connect('main/webfinger',
                    array('action' => 'webfinger'));
        $m->connect('main/ostatus',
                    array('action' => 'ostatusinit'));
        $m->connect('main/ostatus?nickname=:nickname',
                  array('action' => 'ostatusinit'), array('nickname' => '[A-Za-z0-9_-]+'));
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'));
        $m->connect('main/ostatussub',
                    array('action' => 'ostatussub'), array('feed' => '[A-Za-z0-9\.\/\:]+'));

        // PuSH actions
        $m->connect('main/push/hub', array('action' => 'pushhub'));

        $m->connect('main/push/callback/:feed',
                    array('action' => 'pushcallback'),
                    array('feed' => '[0-9]+'));

        // Salmon endpoint
        $m->connect('main/salmon/user/:id',
                    array('action' => 'usersalmon'),
                    array('id' => '[0-9]+'));
        $m->connect('main/salmon/group/:id',
                    array('action' => 'groupsalmon'),
                    array('id' => '[0-9]+'));
        return true;
    }

    /**
     * Set up queue handlers for outgoing hub pushes
     * @param QueueManager $qm
     * @return boolean hook return
     */
    function onEndInitializeQueueManager(QueueManager $qm)
    {
        // Outgoing from our internal PuSH hub
        $qm->connect('hubverify', 'HubVerifyQueueHandler');
        $qm->connect('hubdistrib', 'HubDistribQueueHandler');
        $qm->connect('hubout', 'HubOutQueueHandler');

        // Incoming from a foreign PuSH hub
        $qm->connect('pushinput', 'PushInputQueueHandler');
        return true;
    }

    /**
     * Put saved notices into the queue for pubsub distribution.
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        $transports[] = 'hubdistrib';
        return true;
    }

    /**
     * Set up a PuSH hub link to our internal link for canonical timeline
     * Atom feeds for users and groups.
     */
    function onStartApiAtom($feed)
    {
        $id = null;

        if ($feed instanceof AtomUserNoticeFeed) {
            $salmonAction = 'usersalmon';
            $user = $feed->getUser();
            $id   = $user->id;
            $profile = $user->getProfile();
            $feed->setActivitySubject($profile->asActivityNoun('subject'));
        } else if ($feed instanceof AtomGroupNoticeFeed) {
            $salmonAction = 'groupsalmon';
            $group = $feed->getGroup();
            $id = $group->id;
            $feed->setActivitySubject($group->asActivitySubject());
        } else {
            return true;
        }

        if (!empty($id)) {
            $hub = common_config('ostatus', 'hub');
            if (empty($hub)) {
                // Updates will be handled through our internal PuSH hub.
                $hub = common_local_url('pushhub');
            }
            $feed->addLink($hub, array('rel' => 'hub'));

            // Also, we'll add in the salmon link
            $salmon = common_local_url($salmonAction, array('id' => $id));
            $feed->addLink($salmon, array('rel' => 'salmon'));
        }

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        $map = array('activityverb' => 'activity',
                     'activityobject' => 'activity',
                     'activityutils' => 'activity');
        if (isset($map[$lower])) {
            $lower = $map[$lower];
        }
        $files = array("$base/classes/$cls.php",
                       "$base/lib/$lower.php");
        if (substr($lower, -6) == 'action') {
            $files[] = "$base/actions/" . substr($lower, 0, -6) . ".php";
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                include_once $file;
                return false;
            }
        }
        return true;
    }

    /**
     * Add in an OStatus subscribe button
     */
    function onStartProfileRemoteSubscribe($output, $profile)
    {
        $cur = common_current_user();

        if (empty($cur)) {
            // Add an OStatus subscribe
            $output->elementStart('li', 'entity_subscribe');
            $url = common_local_url('ostatusinit',
                                    array('nickname' => $profile->nickname));
            $output->element('a', array('href' => $url,
                                        'class' => 'entity_remote_subscribe'),
                                _m('Subscribe'));

            $output->elementEnd('li');
        }

        return false;
    }

    /**
     * Check if we've got remote replies to send via Salmon.
     *
     * @fixme push webfinger lookup & sending to a background queue
     * @fixme also detect short-form name for remote subscribees where not ambiguous
     */

    function onEndNoticeSave($notice)
    {
        $mentioned = $notice->getReplies();

        foreach ($mentioned as $profile_id) {

            $oprofile = Ostatus_profile::staticGet('profile_id', $profile_id);

            if (!empty($oprofile) && !empty($oprofile->salmonuri)) {

                // FIXME: this needs to go out in a queue handler

                $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
                $xml .= $notice->asAtomEntry(true, true);

                $salmon = new Salmon();
                $salmon->post($oprofile->salmonuri, $xml);
            }
        }
    }

    /**
     *
     */

    function onEndFindMentions($sender, $text, &$mentions)
    {
        preg_match_all('/(?:^|\s+)@((?:\w+\.)*\w+@(?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+)/',
                       $text,
                       $wmatches,
                       PREG_OFFSET_CAPTURE);

        foreach ($wmatches[1] as $wmatch) {

            $webfinger = $wmatch[0];

            $oprofile = Ostatus_profile::ensureWebfinger($webfinger);

            if (!empty($oprofile)) {

                $profile = $oprofile->localProfile();

                $mentions[] = array('mentioned' => array($profile),
                                    'text' => $wmatch[0],
                                    'position' => $wmatch[1],
                                    'url' => $profile->profileurl);
            }
        }

        return true;
    }

    /**
     * Notify remote server and garbage collect unused feeds on unsubscribe.
     * @fixme send these operations to background queues
     *
     * @param User $user
     * @param Profile $other
     * @return hook return value
     */
    function onEndUnsubscribe($profile, $other)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $other->id);

        if (empty($oprofile)) {
            return true;
        }

        // Drop the PuSH subscription if there are no other subscribers.

        if ($other->subscriberCount() == 0) {
            common_log(LOG_INFO, "Unsubscribing from now-unused feed $oprofile->feeduri");
            $oprofile->unsubscribe();
        }

        $act = new Activity();

        $act->verb = ActivityVerb::UNFOLLOW;

        $act->id   = TagURI::mint('unfollow:%d:%d:%s',
                                  $profile->id,
                                  $other->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Unfollow");
        $act->content = sprintf(_("%s stopped following %s."),
                               $profile->getBestName(),
                               $other->getBestName());

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromProfile($other);

        $oprofile->notifyActivity($act);

        return true;
    }

    /**
     * Make sure necessary tables are filled out.
     */
    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('ostatus_profile', Ostatus_profile::schemaDef());
        $schema->ensureTable('feedsub', FeedSub::schemaDef());
        $schema->ensureTable('hubsub', HubSub::schemaDef());
        return true;
    }

    function onEndShowStatusNetStyles($action) {
        $action->cssLink(common_path('plugins/OStatus/theme/base/css/ostatus.css'));
        return true;
    }

    function onEndShowStatusNetScripts($action) {
        $action->script(common_path('plugins/OStatus/js/ostatus.js'));
        return true;
    }

    /**
     * Override the "from ostatus" bit in notice lists to link to the
     * original post and show the domain it came from.
     *
     * @param Notice in $notice
     * @param string out &$name
     * @param string out &$url
     * @param string out &$title
     * @return mixed hook return code
     */
    function onStartNoticeSourceLink($notice, &$name, &$url, &$title)
    {
        if ($notice->source == 'ostatus') {
            $bits = parse_url($notice->uri);
            $domain = $bits['host'];

            $name = $domain;
            $url = $notice->uri;
            $title = sprintf(_m("Sent from %s via OStatus"), $domain);
            return false;
        }
    }

    /**
     * Send incoming PuSH feeds for OStatus endpoints in for processing.
     *
     * @param FeedSub $feedsub
     * @param DOMDocument $feed
     * @return mixed hook return code
     */
    function onStartFeedSubReceive($feedsub, $feed)
    {
        $oprofile = Ostatus_profile::staticGet('feeduri', $feedsub->uri);
        if ($oprofile) {
            $oprofile->processFeed($feed);
        } else {
            common_log(LOG_DEBUG, "No ostatus profile for incoming feed $feedsub->uri");
        }
    }

    function onEndSubscribe($subscriber, $other)
    {
        $user = User::staticGet('id', $subscriber->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $other->id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FOLLOW;

        $act->id   = TagURI::mint('follow:%d:%d:%s',
                                  $subscriber->id,
                                  $other->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Follow");
        $act->content = sprintf(_("%s is now following %s."),
                               $subscriber->getBestName(),
                               $other->getBestName());

        $act->actor   = ActivityObject::fromProfile($subscriber);
        $act->object  = ActivityObject::fromProfile($other);

        $oprofile->notifyActivity($act);

        return true;
    }

    /**
     * Notify remote users when their notices get favorited.
     *
     * @param Profile or User $profile of local user doing the faving
     * @param Notice $notice being favored
     * @return hook return value
     */

    function onEndFavorNotice(Profile $profile, Notice $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $notice->profile_id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::FAVORITE;
        $act->id   = TagURI::mint('favor:%d:%d:%s',
                                  $profile->id,
                                  $notice->id,
                                  common_date_iso8601(time()));

        $act->time    = time();
        $act->title   = _("Favor");
        $act->content = sprintf(_("%s marked notice %s as a favorite."),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromNotice($notice);

        $oprofile->notifyActivity($act);

        return true;
    }

    /**
     * Notify remote users when their notices get de-favorited.
     *
     * @param Profile $profile Profile person doing the de-faving
     * @param Notice  $notice  Notice being favored
     *
     * @return hook return value
     */

    function onEndDisfavorNotice(Profile $profile, Notice $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (empty($user)) {
            return true;
        }

        $oprofile = Ostatus_profile::staticGet('profile_id', $notice->profile_id);

        if (empty($oprofile)) {
            return true;
        }

        $act = new Activity();

        $act->verb = ActivityVerb::UNFAVORITE;
        $act->id   = TagURI::mint('disfavor:%d:%d:%s',
                                  $profile->id,
                                  $notice->id,
                                  common_date_iso8601(time()));
        $act->time    = time();
        $act->title   = _("Disfavor");
        $act->content = sprintf(_("%s marked notice %s as no longer a favorite."),
                               $profile->getBestName(),
                               $notice->uri);

        $act->actor   = ActivityObject::fromProfile($profile);
        $act->object  = ActivityObject::fromNotice($notice);

        $oprofile->notifyActivity($act);

        return true;
    }

    function onStartGetProfileUri($profile, &$uri)
    {
        $oprofile = Ostatus_profile::staticGet('profile_id', $profile->id);
        if (!empty($oprofile)) {
            $uri = $oprofile->uri;
            return false;
        }
        return true;
    }
}
