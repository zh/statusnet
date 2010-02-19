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

class Ostatus_profile extends Memcached_DataObject
{
    public $__table = 'ostatus_profile';

    public $uri;

    public $profile_id;
    public $group_id;

    public $feeduri;
    public $salmonuri;

    public $created;
    public $modified;

    public /*static*/ function staticGet($k, $v=null)
    {
        return parent::staticGet(__CLASS__, $k, $v);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('uri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT,
                     'group_id' => DB_DATAOBJECT_INT,
                     'feeduri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'salmonuri' =>  DB_DATAOBJECT_STR,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('uri', 'varchar',
                                   255, false, 'PRI'),
                     new ColumnDef('profile_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('group_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('feeduri', 'varchar',
                                   255, false, 'UNI'),
                     new ColumnDef('salmonuri', 'text',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return array('uri' => 'K', 'profile_id' => 'U', 'group_id' => 'U', 'feeduri' => 'U');
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localProfile()
    {
        if ($this->profile_id) {
            return Profile::staticGet('id', $this->profile_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        if ($this->group_id) {
            return User_group::staticGet('id', $this->group_id);
        }
        return null;
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     * @return string
     */
    function asActivityNoun($element)
    {
        $xs = new XMLStringer(true);

        $avatarHref = Avatar::defaultImage(AVATAR_PROFILE_SIZE);
        $avatarType = 'image/png';
        if ($this->isGroup()) {
            $type = 'http://activitystrea.ms/schema/1.0/group';
            $self = $this->localGroup();

            // @fixme put a standard getAvatar() interface on groups too
            if ($self->homepage_logo) {
                $avatarHref = $self->homepage_logo;
                $map = array('png' => 'image/png',
                             'jpg' => 'image/jpeg',
                             'jpeg' => 'image/jpeg',
                             'gif' => 'image/gif');
                $extension = pathinfo(parse_url($avatarHref, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (isset($map[$extension])) {
                    $avatarType = $map[$extension];
                }
            }
        } else {
            $type = 'http://activitystrea.ms/schema/1.0/person';
            $self = $this->localProfile();
            $avatar = $self->getAvatar(AVATAR_PROFILE_SIZE);
            if ($avatar) {
                $avatarHref = $avatar->
                $avatarType = $avatar->mediatype;
            }
        }
        $xs->elementStart('activity:' . $element);
        $xs->element(
            'activity:object-type',
            null,
            $type
        );
        $xs->element(
            'id',
            null,
            $this->uri); // ?
        $xs->element('title', null, $self->getBestName());

        $xs->element(
            'link', array(
                'type' => $avatarType,
                'href' => $avatarHref
            ),
            ''
        );

        $xs->elementEnd('activity:' . $element);

        return $xs->getString();
    }

    /**
     * Damn dirty hack!
     */
    function isGroup()
    {
        return (strpos($this->feeduri, '/groups/') !== false);
    }

    /**
     * Subscribe a local user to this remote user.
     * PuSH subscription will be started if necessary, and we'll
     * send a Salmon notification to the remote server if available
     * notifying them of the sub.
     *
     * @param User $user
     * @return boolean success
     * @throws FeedException
     */
    public function subscribeLocalToRemote(User $user)
    {
        if ($this->isGroup()) {
            throw new ServerException("Can't subscribe to a remote group");
        }

        if ($this->subscribe()) {
            if ($user->subscribeTo($this->localProfile())) {
                $this->notify($user->getProfile(), ActivityVerb::FOLLOW, $this);
                return true;
            }
        }
        return false;
    }

    /**
     * Mark this remote profile as subscribing to the given local user,
     * and send appropriate notifications to the user.
     *
     * This will generally be in response to a subscription notification
     * from a foreign site to our local Salmon response channel.
     *
     * @param User $user
     * @return boolean success
     */
    public function subscribeRemoteToLocal(User $user)
    {
        if ($this->isGroup()) {
            throw new ServerException("Remote groups can't subscribe to local users");
        }

        // @fixme use regular channels for subbing, once they accept remote profiles
        $sub = new Subscription();
        $sub->subscriber = $this->profile_id;
        $sub->subscribed = $user->id;
        $sub->created = common_sql_now(); // current time

        if ($sub->insert()) {
            // @fixme use subs_notify() if refactored to take profiles?
            mail_subscribe_notify_profile($user, $this->localProfile());
            return true;
        }
        return false;
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe($mode='subscribe')
    {
        $feedsub = FeedSub::ensureFeed($this->feeduri);
        if ($feedsub->sub_state == 'active' || $feedsub->sub_state == 'subscribe') {
            return true;
        } else if ($feedsub->sub_state == '' || $feedsub->sub_state == 'inactive') {
            return $feedsub->subscribe();
        } else if ('unsubscribe') {
            throw new FeedSubException("Unsub is pending, can't subscribe...");
        }
    }

    /**
     * Send a PuSH unsubscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function unsubscribe() {
        $feedsub = FeedSub::staticGet('uri', $this->feeduri);
        if ($feedsub->sub_state == 'active') {
            return $feedsub->unsubscribe();
        } else if ($feedsub->sub_state == '' || $feedsub->sub_state == 'inactive' || $feedsub->sub_state == 'unsubscribe') {
            return true;
        } else if ($feedsub->sub_state == 'subscribe') {
            throw new FeedSubException("Feed is awaiting subscription, can't unsub...");
        }
    }

    /**
     * Send an Activity Streams notification to the remote Salmon endpoint,
     * if so configured.
     *
     * @param Profile $actor
     * @param $verb eg Activity::SUBSCRIBE or Activity::JOIN
     * @param $object object of the action; if null, the remote entity itself is assumed
     */
    public function notify(Profile $actor, $verb, $object=null)
    {
        if ($object == null) {
            $object = $this;
        }
        if ($this->salmonuri) {
            $text = 'update'; // @fixme
            $id = 'tag:' . common_config('site', 'server') .
                ':' . $verb .
                ':' . $actor->id .
                ':' . time(); // @fixme

            //$entry = new Atom10Entry();
            $entry = new XMLStringer();
            $entry->elementStart('entry');
            $entry->element('id', null, $id);
            $entry->element('title', null, $text);
            $entry->element('summary', null, $text);
            $entry->element('published', null, common_date_w3dtf(time()));

            $entry->element('activity:verb', null, $verb);
            $entry->raw($actor->asAtomAuthor());
            $entry->raw($actor->asActivityActor());
            $entry->raw($object->asActivityNoun('object'));
            $entry->elementEnd('entry');

            $feed = $this->atomFeed($actor);
            $feed->addEntry($entry);

            $xml = $feed->getString();
            common_log(LOG_INFO, "Posting to Salmon endpoint $salmon: $xml");

            $salmon = new Salmon(); // ?
            $salmon->post($this->salmonuri, $xml);
        }
    }

    function getBestName()
    {
        if ($this->isGroup()) {
            return $this->localGroup()->getBestName();
        } else {
            return $this->localProfile()->getBestName();
        }
    }

    function atomFeed($actor)
    {
        $feed = new Atom10Feed();
        // @fixme should these be set up somewhere else?
        $feed->addNamespace('activity', 'http://activitystrea.ms/spec/1.0/');
        $feed->addNamespace('thr', 'http://purl.org/syndication/thread/1.0');
        $feed->addNamespace('georss', 'http://www.georss.org/georss');
        $feed->addNamespace('ostatus', 'http://ostatus.org/schema/1.0');

        $taguribase = common_config('integration', 'taguri');
        $feed->setId("tag:{$taguribase}:UserTimeline:{$actor->id}"); // ???

        $feed->setTitle($actor->getBestName() . ' timeline'); // @fixme
        $feed->setUpdated(time());
        $feed->setPublished(time());

        $feed->addLink(common_local_url('ApiTimelineUser',
                                        array('id' => $actor->id,
                                              'type' => 'atom')),
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));

        $feed->addLink(common_local_url('userbyid',
                                        array('id' => $actor->id)),
                       array('rel' => 'alternate',
                             'type' => 'text/html'));

        return $feed;
    }

    /**
     * Read and post notices for updates from the feed.
     * Currently assumes that all items in the feed are new,
     * coming from a PuSH hub.
     *
     * @param DOMDocument $feed
     */
    public function processFeed($feed)
    {
        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if ($entries->length == 0) {
            common_log(LOG_ERR, __METHOD__ . ": no entries in feed update, ignoring");
            return;
        }

        for ($i = 0; $i < $entries->length; $i++) {
            $entry = $entries->item($i);
            $this->processEntry($entry, $feed);
        }
    }

    /**
     * Process a posted entry from this feed source.
     *
     * @param DOMElement $entry
     * @param DOMElement $feed for context
     */
    protected function processEntry($entry, $feed)
    {
        $activity = new Activity($entry, $feed);

        $debug = var_export($activity, true);
        common_log(LOG_DEBUG, $debug);

        if ($activity->verb == ActivityVerb::POST) {
            $this->processPost($activity);
        } else {
            common_log(LOG_INFO, "Ignoring activity with unrecognized verb $activity->verb");
        }
    }

    /**
     * Process an incoming post activity from this remote feed.
     * @param Activity $activity
     */
    protected function processPost($activity)
    {
        if ($this->isGroup()) {
            // @fixme validate these profiles in some way!
            $oprofile = self::ensureActorProfile($activity);
        } else {
            $actorUri = self::getActorProfileURI($activity);
            if ($actorUri == $this->uri) {
                // @fixme check if profile info has changed and update it
            } else {
                // @fixme drop or reject the messages once we've got the canonical profile URI recorded sanely
                common_log(LOG_INFO, "OStatus: Warning: non-group post with unexpected author: $actorUri expected $this->uri");
                //return;
            }
            $oprofile = $this;
        }

        if ($activity->object->link) {
            $sourceUri = $activity->object->link;
        } else if (preg_match('!^https?://!', $activity->object->id)) {
            $sourceUri = $activity->object->id;
        } else {
            common_log(LOG_INFO, "OStatus: ignoring post with no source link: id $activity->object->id");
            return;
        }

        $dupe = Notice::staticGet('uri', $sourceUri);
        if ($dupe) {
            common_log(LOG_INFO, "OStatus: ignoring duplicate post: $noticeLink");
            return;
        }

        // @fixme sanitize and save HTML content if available
        $content = $activity->object->title;

        $params = array('is_local' => Notice::REMOTE_OMB,
                        'uri' => $sourceUri);

        $location = $this->getEntryLocation($activity->entry);
        if ($location) {
            $params['lat'] = $location->lat;
            $params['lon'] = $location->lon;
            if ($location->location_id) {
                $params['location_ns'] = $location->location_ns;
                $params['location_id'] = $location->location_id;
            }
        }

        // @fixme save detailed ostatus source info
        // @fixme ensure that groups get handled correctly

        $saved = Notice::saveNew($oprofile->localProfile()->id,
                                 $content,
                                 'ostatus',
                                 $params);
    }

    /**
     * @param string $profile_url
     * @return Ostatus_profile
     * @throws FeedSubException
     */
    public static function ensureProfile($profile_uri)
    {
        // Get the canonical feed URI and check it
        $discover = new FeedDiscovery();
        $feeduri = $discover->discoverFromURL($profile_uri);

        $feedsub = FeedSub::ensureFeed($feeduri, $discover->feed);
        $huburi = $discover->getAtomLink('hub');
        $salmonuri = $discover->getAtomLink('salmon');

        if (!$huburi) {
            // We can only deal with folks with a PuSH hub
            throw new FeedSubNoHubException();
        }

        // Ok this is going to be a terrible hack!
        // Won't be suitable for groups, empty feeds, or getting
        // info that's only available on the profile page.
        $entries = $discover->feed->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if (!$entries || $entries->length == 0) {
            throw new FeedSubException('empty feed');
        }
        $first = new Activity($entries->item(0), $discover->feed);
        return self::ensureActorProfile($first, $feeduri);
    }

    /**
     * Download and update given avatar image
     * @param string $url
     * @throws Exception in various failure cases
     */
    protected function updateAvatar($url)
    {
        // @fixme this should be better encapsulated
        // ripped from oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        copy($url, $temp_filename);

        // @fixme should we be using different ids?
        $imagefile = new ImageFile($this->id, $temp_filename);
        $filename = Avatar::filename($this->id,
                                     image_type_to_extension($imagefile->type),
                                     null,
                                     common_timestamp());
        rename($temp_filename, Avatar::path($filename));
        if ($this->isGroup()) {
            $group = $this->localGroup();
            $group->setOriginal($filename);
        } else {
            $profile = $this->localProfile();
            $profile->setOriginal($filename);
        }
    }

    /**
     * Get an appropriate avatar image source URL, if available.
     *
     * @param ActivityObject $actor
     * @param DOMElement $feed
     * @return string
     */
    protected static function getAvatar($actor, $feed)
    {
        $url = '';
        $icon = '';
        if ($actor->avatar) {
            $url = trim($actor->avatar);
        }
        if (!$url) {
            // Check <atom:logo> and <atom:icon> on the feed
            $els = $feed->childNodes();
            if ($els && $els->length) {
                for ($i = 0; $i < $els->length; $i++) {
                    $el = $els->item($i);
                    if ($el->namespaceURI == Activity::ATOM) {
                        if (empty($url) && $el->localName == 'logo') {
                            $url = trim($el->textContent);
                            break;
                        }
                        if (empty($icon) && $el->localName == 'icon') {
                            // Use as a fallback
                            $icon = trim($el->textContent);
                        }
                    }
                }
            }
            if ($icon && !$url) {
                $url = $icon;
            }
        }
        if ($url) {
            $opts = array('allowed_schemes' => array('http', 'https'));
            if (Validate::uri($url, $opts)) {
                return $url;
            }
        }
        return common_path('plugins/OStatus/images/96px-Feed-icon.svg.png');
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the actor
     * in a given Activity Streams activity.
     *
     * @param Activity $activity
     * @param string $feeduri if we already know the canonical feed URI!
     * @return Ostatus_profile
     */
    public static function ensureActorProfile($activity, $feeduri=null)
    {
        $profile = self::getActorProfile($activity);
        if (!$profile) {
            $profile = self::createActorProfile($activity, $feeduri);
        }
        return $profile;
    }

    /**
     * @param Activity $activity
     * @return mixed matching Ostatus_profile or false if none known
     */
    protected static function getActorProfile($activity)
    {
        $homeuri = self::getActorProfileURI($activity);
        return self::staticGet('uri', $homeuri);
    }

    /**
     * @param Activity $activity
     * @return string
     * @throws ServerException
     */
    protected static function getActorProfileURI($activity)
    {
        $opts = array('allowed_schemes' => array('http', 'https'));
        $actor = $activity->actor;
        if ($actor->id && Validate::uri($actor->id, $opts)) {
            return $actor->id;
        }
        if ($actor->link && Validate::uri($actor->link, $opts)) {
            return $actor->link;
        }
        throw new ServerException("No author ID URI found");
    }

    /**
     * @fixme validate stuff somewhere
     */
    protected static function createActorProfile($activity, $feeduri=null)
    {
        $actor = $activity->actor;
        $homeuri = self::getActorProfileURI($activity);
        $nickname = self::getAuthorNick($activity);
        $avatar = self::getAvatar($actor, $feed);

        if (!$homeuri) {
            common_log(LOG_DEBUG, __METHOD__ . " empty actor profile URI: " . var_export($activity, true));
            throw new ServerException("No profile URI");
        }

        $profile = new Profile();
        $profile->nickname   = $nickname;
        $profile->fullname   = $actor->displayName;
        $profile->homepage   = $actor->link; // @fixme
        $profile->profileurl = $homeuri;
        // @fixme bio
        // @fixme tags/categories
        // @fixme location?
        // @todo tags from categories
        // @todo lat/lon/location?

        $ok = $profile->insert();
        if (!$ok) {
            throw new ServerException("Can't save local profile");
        }

        // @fixme either need to do feed discovery here
        // or need to split out some of the feed stuff
        // so we can leave it empty until later.
        $oprofile = new Ostatus_profile();
        $oprofile->uri = $homeuri;
        if ($feeduri) {
            $oprofile->feeduri = $feeduri;
        }
        $oprofile->profile_id = $profile->id;

        $ok = $oprofile->insert();
        if ($ok) {
            $oprofile->updateAvatar($avatar);
            return $oprofile;
        } else {
            throw new ServerException("Can't save OStatus profile");
        }
    }

    /**
     * @fixme move this into Activity?
     * @param Activity $activity
     * @return string
     */
    protected static function getAuthorNick($activity)
    {
        // @fixme not technically part of the actor?
        foreach (array($activity->entry, $activity->feed) as $source) {
            $author = ActivityUtils::child($source, 'author', Activity::ATOM);
            if ($author) {
                $name = ActivityUtils::child($author, 'name', Activity::ATOM);
                if ($name) {
                    return trim($name->textContent);
                }
            }
        }
        return false;
    }

}
