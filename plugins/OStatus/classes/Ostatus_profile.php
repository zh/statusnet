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
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

/*
PuSH subscription flow:

    $profile->subscribe()
        generate random verification token
            save to verify_token
        sends a sub request to the hub...

    main/push/callback
        hub sends confirmation back to us via GET
        We verify the request, then echo back the challenge.
        On our end, we save the time we subscribed and the lease expiration

    main/push/callback
        hub sends us updates via POST

*/

class FeedDBException extends FeedSubException
{
    public $obj;

    function __construct($obj)
    {
        parent::__construct('Database insert failure');
        $this->obj = $obj;
    }
}

class Ostatus_profile extends Memcached_DataObject
{
    public $__table = 'ostatus_profile';

    public $id;
    public $profile_id;
    public $group_id;

    public $feeduri;
    public $homeuri;

    // PuSH subscription data
    public $huburi;
    public $secret;
    public $verify_token;
    public $sub_state; // subscribe, active, unsubscribe
    public $sub_start;
    public $sub_end;

    public $salmonuri;

    public $created;
    public $lastupdate;

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
        return array('id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT,
                     'group_id' => DB_DATAOBJECT_INT,
                     'feeduri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'homeuri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'huburi' =>  DB_DATAOBJECT_STR,
                     'secret' => DB_DATAOBJECT_STR,
                     'verify_token' => DB_DATAOBJECT_STR,
                     'sub_state' => DB_DATAOBJECT_STR,
                     'sub_start' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'sub_end' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'salmonuri' =>  DB_DATAOBJECT_STR,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'lastupdate' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('id', 'integer',
                                   /*size*/ null,
                                   /*nullable*/ false,
                                   /*key*/ 'PRI',
                                   /*default*/ '0',
                                   /*extra*/ null,
                                   /*auto_increment*/ true),
                     new ColumnDef('profile_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('group_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('feeduri', 'varchar',
                                   255, false, 'UNI'),
                     new ColumnDef('homeuri', 'varchar',
                                   255, false),
                     new ColumnDef('huburi', 'text',
                                   null, true),
                     new ColumnDef('verify_token', 'varchar',
                                   32, true),
                     new ColumnDef('secret', 'varchar',
                                   64, true),
                     new ColumnDef('sub_state', "enum('subscribe','active','unsubscribe')",
                                   null, true),
                     new ColumnDef('sub_start', 'datetime',
                                   null, true),
                     new ColumnDef('sub_end', 'datetime',
                                   null, true),
                     new ColumnDef('salmonuri', 'text',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('lastupdate', 'datetime',
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
        return array('id' => 'K', 'profile_id' => 'U', 'group_id' => 'U', 'feeduri' => 'U');
    }

    function sequenceKey()
    {
        return array('id', true, false);
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
     * @param FeedMunger $munger
     * @param boolean $isGroup is this a group record?
     * @return Ostatus_profile
     */
    public static function ensureProfile($munger)
    {
        $profile = $munger->ostatusProfile();

        $current = self::staticGet('feeduri', $profile->feeduri);
        if ($current) {
            // @fixme we should probably update info as necessary
            return $current;
        }

        $profile->query('BEGIN');

        try {
            $local = $munger->profile();

            if ($profile->isGroup()) {
                $group = new User_group();
                $group->nickname = $local->nickname . '@remote'; // @fixme
                $group->fullname = $local->fullname;
                $group->homepage = $local->homepage;
                $group->location = $local->location;
                $group->created = $local->created;
                $group->insert();
                if (empty($result)) {
                    throw new FeedDBException($group);
                }
                $profile->group_id = $group->id;
            } else {
                $result = $local->insert();
                if (empty($result)) {
                    throw new FeedDBException($local);
                }
                $profile->profile_id = $local->id;
            }

            $profile->created = common_sql_now();
            $profile->lastupdate = common_sql_now();
            $result = $profile->insert();
            if (empty($result)) {
                throw new FeedDBException($profile);
            }

            $profile->query('COMMIT');
        } catch (FeedDBException $e) {
            common_log_db_error($e->obj, 'INSERT', __FILE__);
            $profile->query('ROLLBACK');
            return false;
        }

        $avatar = $munger->getAvatar();
        if ($avatar) {
            try {
                $profile->updateAvatar($avatar);
            } catch (Exception $e) {
                common_log(LOG_ERR, "Exception setting OStatus avatar: " .
                                    $e->getMessage());
            }
        }

        return $profile;
    }

    /**
     * Download and update given avatar image
     * @param string $url
     * @throws Exception in various failure cases
     */
    public function updateAvatar($url)
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
            $this->homeuri); // ?
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
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe($mode='subscribe')
    {
        if ($this->sub_state != '') {
            throw new ServerException("Attempting to start PuSH subscription to feed in state $this->sub_state");
        }
        if (empty($this->huburi)) {
            if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                return true;
            } else {
                throw new ServerException("Attempting to start PuSH subscription for feed with no hub");
            }
        }

        return $this->doSubscribe('subscribe');
    }

    /**
     * Send a PuSH unsubscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function unsubscribe() {
        if ($this->sub_state != 'active') {
            throw new ServerException("Attempting to end PuSH subscription to feed in state $this->sub_state");
        }
        if (empty($this->huburi)) {
            if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                return true;
            } else {
                throw new ServerException("Attempting to end PuSH subscription for feed with no hub");
            }
        }

        return $this->doSubscribe('unsubscribe');
    }

    protected function doSubscribe($mode)
    {
        $orig = clone($this);
        $this->verify_token = common_good_rand(16);
        if ($mode == 'subscribe') {
            $this->secret = common_good_rand(32);
        }
        $this->sub_state = $mode;
        $this->update($orig);
        unset($orig);

        try {
            $callback = common_local_url('pushcallback', array('feed' => $this->id));
            $headers = array('Content-Type: application/x-www-form-urlencoded');
            $post = array('hub.mode' => $mode,
                          'hub.callback' => $callback,
                          'hub.verify' => 'async',
                          'hub.verify_token' => $this->verify_token,
                          'hub.secret' => $this->secret,
                          //'hub.lease_seconds' => 0,
                          'hub.topic' => $this->feeduri);
            $client = new HTTPClient();
            $response = $client->post($this->huburi, $headers, $post);
            $status = $response->getStatus();
            if ($status == 202) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok, awaiting verification callback');
                return true;
            } else if ($status == 204) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok and verified');
                return true;
            } else if ($status >= 200 && $status < 300) {
                common_log(LOG_ERR, __METHOD__ . ": sub req returned unexpected HTTP $status: " . $response->getBody());
                return false;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": sub req failed with HTTP $status: " . $response->getBody());
                return false;
            }
        } catch (Exception $e) {
            // wtf!
            common_log(LOG_ERR, __METHOD__ . ": error \"{$e->getMessage()}\" hitting hub $this->huburi subscribing to $this->feeduri");

            $orig = clone($this);
            $this->verify_token = null;
            $this->sub_state = null;
            $this->update($orig);
            unset($orig);

            return false;
        }
    }

    /**
     * Save PuSH subscription confirmation.
     * Sets approximate lease start and end times and finalizes state.
     *
     * @param int $lease_seconds provided hub.lease_seconds parameter, if given
     */
    public function confirmSubscribe($lease_seconds=0)
    {
        $original = clone($this);

        $this->sub_state = 'active';
        $this->sub_start = common_sql_date(time());
        if ($lease_seconds > 0) {
            $this->sub_end = common_sql_date(time() + $lease_seconds);
        } else {
            $this->sub_end = null;
        }
        $this->lastupdate = common_sql_date();

        return $this->update($original);
    }

    /**
     * Save PuSH unsubscription confirmation.
     * Wipes active PuSH sub info and resets state.
     */
    public function confirmUnsubscribe()
    {
        $original = clone($this);

        $this->verify_token = null;
        $this->secret = null;
        $this->sub_state = null;
        $this->sub_start = null;
        $this->sub_end = null;
        $this->lastupdate = common_sql_date();

        return $this->update($original);
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

            $entry = new Atom10Entry();
            $entry->elementStart('entry');
            $entry->element('id', null, $id);
            $entry->element('title', null, $text);
            $entry->element('summary', null, $text);
            $entry->element('published', null, common_date_w3dtf());

            $entry->element('activity:verb', null, $verb);
            $entry->raw($profile->asAtomAuthor());
            $entry->raw($profile->asActivityActor());
            $entry->raw($object->asActivityNoun('object'));
            $entry->elmentEnd('entry');

            $feed = $this->atomFeed($actor);
            $feed->initFeed();
            $feed->addEntry($entry);
            $feed->renderEntries();
            $feed->endFeed();

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
        $feed->addNamesapce('thr', 'http://purl.org/syndication/thread/1.0');
        $feed->addNamespace('georss', 'http://www.georss.org/georss');
        $feed->addNamespace('ostatus', 'http://ostatus.org/schema/1.0');

        $taguribase = common_config('integration', 'taguri');
        $feed->setId("tag:{$taguribase}:UserTimeline:{$actor->id}"); // ???

        $feed->setTitle($actor->getBestName() . ' timeline'); // @fixme
        $feed->setUpdated(time());
        $feed->setPublished(time());

        $feed->addLink(common_url('ApiTimelineUser',
                                  array('id' => $actor->id,
                                        'type' => 'atom')),
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));

        $feed->addLink(common_url('userbyid',
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
     * @param string $post source of Atom or RSS feed
     * @param string $hmac X-Hub-Signature header, if present
     */
    public function postUpdates($post, $hmac)
    {
        common_log(LOG_INFO, __METHOD__ . ": packet for \"$this->feeduri\"! $hmac $post");

        if ($this->sub_state != 'active') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH for inactive feed $this->feeduri (in state '$this->sub_state')");
            return;
        }

        if ($post === '') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring empty post");
            return;
        }

        if (!$this->validatePushSig($post, $hmac)) {
            // Per spec we silently drop input with a bad sig,
            // while reporting receipt to the server.
            return;
        }

        $feed = new DOMDocument();
        if (!$feed->loadXML($post)) {
            // @fixme might help to include the err message
            common_log(LOG_ERR, __METHOD__ . ": ignoring invalid XML");
            return;
        }

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
     * Validate the given Atom chunk and HMAC signature against our
     * shared secret that was set up at subscription time.
     *
     * If we don't have a shared secret, there should be no signature.
     * If we we do, our the calculated HMAC should match theirs.
     *
     * @param string $post raw XML source as POSTed to us
     * @param string $hmac X-Hub-Signature HTTP header value, or empty
     * @return boolean true for a match
     */
    protected function validatePushSig($post, $hmac)
    {
        if ($this->secret) {
            if (preg_match('/^sha1=([0-9a-fA-F]{40})$/', $hmac, $matches)) {
                $their_hmac = strtolower($matches[1]);
                $our_hmac = hash_hmac('sha1', $post, $this->secret);
                if ($their_hmac === $our_hmac) {
                    return true;
                }
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bad SHA-1 HMAC: got $their_hmac, expected $our_hmac");
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bogus HMAC '$hmac'");
            }
        } else {
            if (empty($hmac)) {
                return true;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with unexpected HMAC '$hmac'");
            }
        }
        return false;
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
            $oprofile = $this->ensureActorProfile($activity);
        } else {
            $actorUri = $this->getActorProfileURI($activity);
            if ($actorUri == $this->homeuri) {
                // @fixme check if profile info has changed and update it
            } else {
                // @fixme drop or reject the messages once we've got the canonical profile URI recorded sanely
                common_log(LOG_INFO, "OStatus: Warning: non-group post with unexpected author: $actorUri expected $this->homeuri");
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
     * Parse location given as a GeoRSS-simple point, if provided.
     * http://www.georss.org/simple
     *
     * @param feed item $entry
     * @return mixed Location or false
     */
    function getLocation($dom)
    {
        $points = $dom->getElementsByTagNameNS('http://www.georss.org/georss', 'point');
        
        for ($i = 0; $i < $points->length; $i++) {
            $point = $points->item(0)->textContent;
            $point = str_replace(',', ' ', $point); // per spec "treat commas as whitespace"
            $point = preg_replace('/\s+/', ' ', $point);
            $point = trim($point);
            $coords = explode(' ', $point);
            if (count($coords) == 2) {
                list($lat, $lon) = $coords;
                if (is_numeric($lat) && is_numeric($lon)) {
                    common_log(LOG_INFO, "Looking up location for $lat $lon from georss");
                    return Location::fromLatLon($lat, $lon);
                }
            }
            common_log(LOG_ERR, "Ignoring bogus georss:point value $point");
        }

        return false;
    }

    /**
     * Get an appropriate avatar image source URL, if available.
     *
     * @param ActivityObject $actor
     * @param DOMElement $feed
     * @return string
     */
    function getAvatar($actor, $feed)
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
     * @fixme move off of ostatus_profile or static?
     */
    function ensureActorProfile($activity)
    {
        $profile = $this->getActorProfile($activity);
        if (!$profile) {
            $profile = $this->createActorProfile($activity);
        }
        return $profile;
    }

    /**
     * @param Activity $activity
     * @return mixed matching Ostatus_profile or false if none known
     */
    function getActorProfile($activity)
    {
        $homeuri = $this->getActorProfileURI($activity);
        return Ostatus_profile::staticGet('homeuri', $homeuri);
    }

    /**
     * @param Activity $activity
     * @return string
     * @throws ServerException
     */
    function getActorProfileURI($activity)
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
     *
     */
    function createActorProfile($activity)
    {
        $actor = $activity->actor();
        $homeuri = $this->getActivityProfileURI($activity);
        $nickname = $this->getAuthorNick($activity);
        $avatar = $this->getAvatar($actor, $feed);

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
        if ($ok) {
            $this->updateAvatar($profile, $avatar);
        } else {
            throw new ServerException("Can't save local profile");
        }

        // @fixme either need to do feed discovery here
        // or need to split out some of the feed stuff
        // so we can leave it empty until later.
        $oprofile = new Ostatus_profile();
        $oprofile->homeuri = $homeuri;
        $oprofile->profile_id = $profile->id;

        $ok = $oprofile->insert();
        if ($ok) {
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
    function getAuthorNick($activity)
    {
        // @fixme not technically part of the actor?
        foreach (array($activity->entry, $activity->feed) as $source) {
            $author = ActivityUtil::child($source, 'author', Activity::ATOM);
            if ($author) {
                $name = ActivityUtil::child($author, 'name', Activity::ATOM);
                if ($name) {
                    return trim($name->textContent);
                }
            }
        }
        return false;
    }

}
