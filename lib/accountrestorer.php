<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A class for restoring accounts
 * 
 * PHP version 5
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
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A class for restoring accounts
 *
 * This is a clumsy objectification of the functions in restoreuser.php.
 * 
 * Note that it quite illegally uses the OStatus_profile class which may
 * not even exist on this server.
 * 
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class AccountRestorer
{
    private $_trusted = false;

    function loadXML($xml)
    {
        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'feed') {
            throw new Exception("'$filename' is not an Atom feed.");
        }

        return $dom;
    }

    function importActivityStream($user, $doc)
    {
        $feed = $doc->documentElement;

        $subjectEl = ActivityUtils::child($feed, Activity::SUBJECT, Activity::SPEC);

        if (!empty($subjectEl)) {
            $subject = new ActivityObject($subjectEl);
        } else {
            throw new Exception("Feed doesn't have an <activity:subject> element.");
        }

        if (is_null($user)) {
            $user = $this->userFromSubject($subject);
        }

        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');

        $activities = $this->entriesToActivities($entries, $feed);

        // XXX: sort entries here

        foreach ($activities as $activity) {
            try {
                switch ($activity->verb) {
                case ActivityVerb::FOLLOW:
                    $this->subscribeProfile($user, $subject, $activity);
                    break;
                case ActivityVerb::JOIN:
                    $this->joinGroup($user, $activity);
                    break;
                case ActivityVerb::POST:
                    $this->postNote($user, $activity);
                    break;
                default:
                    throw new Exception("Unknown verb: {$activity->verb}");
                }
            } catch (Exception $e) {
                common_log(LOG_WARNING, $e->getMessage());
                continue;
            }
        }
    }

    function subscribeProfile($user, $subject, $activity)
    {
        $profile = $user->getProfile();

        if ($activity->objects[0]->id == $subject->id) {
            if (!$this->_trusted) {
                    throw new Exception("Skipping a pushed subscription.");
            } else {
                $other = $activity->actor;
                $otherUser = User::staticGet('uri', $other->id);

                if (!empty($otherUser)) {
                    $otherProfile = $otherUser->getProfile();
                } else {
                    throw new Exception("Can't force remote user to subscribe.");
                }
                // XXX: don't do this for untrusted input!
                Subscription::start($otherProfile, $profile);
            }
        } else if (empty($activity->actor) 
                   || $activity->actor->id == $subject->id) {

            $other = $activity->objects[0];
            $otherUser = User::staticGet('uri', $other->id);

            if (!empty($otherUser)) {
                $otherProfile = $otherUser->getProfile();
            } else {
                $oprofile = Ostatus_profile::ensureActivityObjectProfile($other);
                $otherProfile = $oprofile->localProfile();
            }

            Subscription::start($profile, $otherProfile);
        } else {
            throw new Exception("This activity seems unrelated to our user.");
        }
    }

    function joinGroup($user, $activity)
    {
        // XXX: check that actor == subject

        $uri = $activity->objects[0]->id;

        $group = User_group::staticGet('uri', $uri);

        if (empty($group)) {
            $oprofile = Ostatus_profile::ensureActivityObjectProfile($activity->objects[0]);
            if (!$oprofile->isGroup()) {
                throw new Exception("Remote profile is not a group!");
            }
            $group = $oprofile->localGroup();
        }

        assert(!empty($group));

        if (Event::handle('StartJoinGroup', array($group, $user))) {
            Group_member::join($group->id, $user->id);
            Event::handle('EndJoinGroup', array($group, $user));
        }
    }

    // XXX: largely cadged from Ostatus_profile::processNote()

    function postNote($user, $activity)
    {
        $note = $activity->objects[0];

        $sourceUri = $note->id;

        $notice = Notice::staticGet('uri', $sourceUri);

        if (!empty($notice)) {
            // This is weird.
            $orig = clone($notice);
            $notice->profile_id = $user->id;
            $notice->update($orig);
            return;
        }

        // Use summary as fallback for content

        if (!empty($note->content)) {
            $sourceContent = $note->content;
        } else if (!empty($note->summary)) {
            $sourceContent = $note->summary;
        } else if (!empty($note->title)) {
            $sourceContent = $note->title;
        } else {
            // @fixme fetch from $sourceUrl?
            // @todo i18n FIXME: use sprintf and add i18n.
            throw new ClientException("No content for notice {$sourceUri}.");
        }

        // Get (safe!) HTML and text versions of the content

        $rendered = $this->purify($sourceContent);
        $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

        $shortened = $user->shortenLinks($content);

        $options = array('is_local' => Notice::LOCAL_PUBLIC,
                         'uri' => $sourceUri,
                         'rendered' => $rendered,
                         'replies' => array(),
                         'groups' => array(),
                         'tags' => array(),
                         'urls' => array());

        // Check for optional attributes...

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

        if ($activity->context) {
            // Any individual or group attn: targets?

            list($options['groups'], $options['replies']) = $this->filterAttention($activity->context->attention);

            // Maintain direct reply associations
            // @fixme what about conversation ID?
            if (!empty($activity->context->replyToID)) {
                $orig = Notice::staticGet('uri',
                                          $activity->context->replyToID);
                if (!empty($orig)) {
                    $options['reply_to'] = $orig->id;
                }
            }

            $location = $activity->context->location;

            if ($location) {
                $options['lat'] = $location->lat;
                $options['lon'] = $location->lon;
                if ($location->location_id) {
                    $options['location_ns'] = $location->location_ns;
                    $options['location_id'] = $location->location_id;
                }
            }
        }

        // Atom categories <-> hashtags

        foreach ($activity->categories as $cat) {
            if ($cat->term) {
                $term = common_canonical_tag($cat->term);
                if ($term) {
                    $options['tags'][] = $term;
                }
            }
        }

        // Atom enclosures -> attachment URLs
        foreach ($activity->enclosures as $href) {
            // @fixme save these locally or....?
            $options['urls'][] = $href;
        }

        $saved = Notice::saveNew($user->id,
                                 $content,
                                 'restore', // TODO: restore the actual source
                                 $options);

        return $saved;
    }

    function filterAttention($attn)
    {
        $groups = array();
        $replies = array();

        foreach (array_unique($attn) as $recipient) {

            // Is the recipient a local user?

            $user = User::staticGet('uri', $recipient);

            if ($user) {
                // @fixme sender verification, spam etc?
                $replies[] = $recipient;
                continue;
            }

            // Is the recipient a remote group?
            $oprofile = Ostatus_profile::ensureProfileURI($recipient);

            if ($oprofile) {
                if (!$oprofile->isGroup()) {
                    // may be canonicalized or something
                    $replies[] = $oprofile->uri;
                }
                continue;
            }

            // Is the recipient a local group?
            // @fixme uri on user_group isn't reliable yet
            // $group = User_group::staticGet('uri', $recipient);
            $id = OStatusPlugin::localGroupFromUrl($recipient);

            if ($id) {
                $group = User_group::staticGet('id', $id);
                if ($group) {
                    // Deliver to all members of this local group if allowed.
                    $profile = $sender->localProfile();
                    if ($profile->isMember($group)) {
                        $groups[] = $group->id;
                    } else {
                        common_log(LOG_INFO, "Skipping reply to local group {$group->nickname} as sender {$profile->id} is not a member");
                    }
                    continue;
                } else {
                    common_log(LOG_INFO, "Skipping reply to bogus group $recipient");
                }
            }
        }

        return array($groups, $replies);
    }
 
   function userFromSubject($subject)
    {
        $user = User::staticGet('uri', $subject->id);

        if (empty($user)) {
            $attrs =
                array('nickname' => Ostatus_profile::getActivityObjectNickname($subject),
                      'uri' => $subject->id);

            $user = User::register($attrs);
        }

        $profile = $user->getProfile();
        Ostatus_profile::updateProfile($profile, $subject);

        // FIXME: Update avatar
        return $user;
    }

    function purify($content)
    {
        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*');
        return htmLawed($content, $config);
    }
}
