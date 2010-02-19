<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Utilities for turning DOMish things into Activityish things
 *
 * Some common functions that I didn't have the bandwidth to try to factor
 * into some kind of reasonable superclass, so just dumped here. Might
 * be useful to have an ActivityObject parent class or something.
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class ActivityUtils
{
    const ATOM = 'http://www.w3.org/2005/Atom';

    const LINK = 'link';
    const REL  = 'rel';
    const TYPE = 'type';
    const HREF = 'href';

    /**
     * Get the permalink for an Activity object
     *
     * @param DOMElement $element A DOM element
     *
     * @return string related link, if any
     */

    static function getPermalink($element)
    {
        return self::getLink($element, 'alternate', 'text/html');
    }

    /**
     * Get the permalink for an Activity object
     *
     * @param DOMElement $element A DOM element
     *
     * @return string related link, if any
     */

    static function getLink($element, $rel, $type=null)
    {
        $links = $element->getElementsByTagnameNS(self::ATOM, self::LINK);

        foreach ($links as $link) {

            $linkRel = $link->getAttribute(self::REL);
            $linkType = $link->getAttribute(self::TYPE);

            if ($linkRel == $rel &&
                (is_null($type) || $linkType == $type)) {
                return $link->getAttribute(self::HREF);
            }
        }

        return null;
    }

    /**
     * Gets the first child element with the given tag
     *
     * @param DOMElement $element   element to pick at
     * @param string     $tag       tag to look for
     * @param string     $namespace Namespace to look under
     *
     * @return DOMElement found element or null
     */

    static function child($element, $tag, $namespace=self::ATOM)
    {
        $els = $element->childNodes;
        if (empty($els) || $els->length == 0) {
            return null;
        } else {
            for ($i = 0; $i < $els->length; $i++) {
                $el = $els->item($i);
                if ($el->localName == $tag && $el->namespaceURI == $namespace) {
                    return $el;
                }
            }
        }
    }

    /**
     * Grab the text content of a DOM element child of the current element
     *
     * @param DOMElement $element   Element whose children we examine
     * @param string     $tag       Tag to look up
     * @param string     $namespace Namespace to use, defaults to Atom
     *
     * @return string content of the child
     */

    static function childContent($element, $tag, $namespace=self::ATOM)
    {
        $el = self::child($element, $tag, $namespace);

        if (empty($el)) {
            return null;
        } else {
            return $el->textContent;
        }
    }
}

/**
 * A noun-ish thing in the activity universe
 *
 * The activity streams spec talks about activity objects, while also having
 * a tag activity:object, which is in fact an activity object. Aaaaaah!
 *
 * This is just a thing in the activity universe. Can be the subject, object,
 * or indirect object (target!) of an activity verb. Rotten name, and I'm
 * propagating it. *sigh*
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class ActivityObject
{
    const ARTICLE   = 'http://activitystrea.ms/schema/1.0/article';
    const BLOGENTRY = 'http://activitystrea.ms/schema/1.0/blog-entry';
    const NOTE      = 'http://activitystrea.ms/schema/1.0/note';
    const STATUS    = 'http://activitystrea.ms/schema/1.0/status';
    const FILE      = 'http://activitystrea.ms/schema/1.0/file';
    const PHOTO     = 'http://activitystrea.ms/schema/1.0/photo';
    const ALBUM     = 'http://activitystrea.ms/schema/1.0/photo-album';
    const PLAYLIST  = 'http://activitystrea.ms/schema/1.0/playlist';
    const VIDEO     = 'http://activitystrea.ms/schema/1.0/video';
    const AUDIO     = 'http://activitystrea.ms/schema/1.0/audio';
    const BOOKMARK  = 'http://activitystrea.ms/schema/1.0/bookmark';
    const PERSON    = 'http://activitystrea.ms/schema/1.0/person';
    const GROUP     = 'http://activitystrea.ms/schema/1.0/group';
    const PLACE     = 'http://activitystrea.ms/schema/1.0/place';
    const COMMENT   = 'http://activitystrea.ms/schema/1.0/comment';
    // ^^^^^^^^^^ tea!

    // Atom elements we snarf

    const TITLE   = 'title';
    const SUMMARY = 'summary';
    const CONTENT = 'content';
    const ID      = 'id';
    const SOURCE  = 'source';

    const NAME  = 'name';
    const URI   = 'uri';
    const EMAIL = 'email';

    public $element;
    public $type;
    public $id;
    public $title;
    public $summary;
    public $content;
    public $link;
    public $source;

    /**
     * Constructor
     *
     * This probably needs to be refactored
     * to generate a local class (ActivityPerson, ActivityFile, ...)
     * based on the object type.
     *
     * @param DOMElement $element DOM thing to turn into an Activity thing
     */

    function __construct($element)
    {
        $this->element = $element;

        if ($element->tagName == 'author') {

            $this->type  = self::PERSON; // XXX: is this fair?
            $this->title = $this->_childContent($element, self::NAME);
            $this->id    = $this->_childContent($element, self::URI);

            if (empty($this->id)) {
                $email = $this->_childContent($element, self::EMAIL);
                if (!empty($email)) {
                    // XXX: acct: ?
                    $this->id = 'mailto:'.$email;
                }
            }

        } else {

            $this->type = $this->_childContent($element, Activity::OBJECTTYPE,
                                               Activity::SPEC);

            if (empty($this->type)) {
                $this->type = ActivityObject::NOTE;
            }

            $this->id      = $this->_childContent($element, self::ID);
            $this->title   = $this->_childContent($element, self::TITLE);
            $this->summary = $this->_childContent($element, self::SUMMARY);
            $this->content = $this->_childContent($element, self::CONTENT);

            $this->source  = $this->_getSource($element);

            $this->link = ActivityUtils::getPermalink($element);

            // XXX: grab PoCo stuff
        }

        // Some per-type attributes...
        if ($this->type == self::PERSON || $this->type == self::GROUP) {
            $this->displayName = $this->title;

            // @fixme we may have multiple avatars with different resolutions specified
            $this->avatar = ActivityUtils::getLink($element, 'avatar');
        }
    }

    private function _childContent($element, $tag, $namespace=ActivityUtils::ATOM)
    {
        return ActivityUtils::childContent($element, $tag, $namespace);
    }

    // Try to get a unique id for the source feed

    private function _getSource($element)
    {
        $sourceEl = ActivityUtils::child($element, 'source');

        if (empty($sourceEl)) {
            return null;
        } else {
            $href = ActivityUtils::getLink($sourceEl, 'self');
            if (!empty($href)) {
                return $href;
            } else {
                return ActivityUtils::childContent($sourceEl, 'id');
            }
        }
    }
}

/**
 * Utility class to hold a bunch of constant defining default verb types
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class ActivityVerb
{
    const POST     = 'http://activitystrea.ms/schema/1.0/post';
    const SHARE    = 'http://activitystrea.ms/schema/1.0/share';
    const SAVE     = 'http://activitystrea.ms/schema/1.0/save';
    const FAVORITE = 'http://activitystrea.ms/schema/1.0/favorite';
    const PLAY     = 'http://activitystrea.ms/schema/1.0/play';
    const FOLLOW   = 'http://activitystrea.ms/schema/1.0/follow';
    const FRIEND   = 'http://activitystrea.ms/schema/1.0/make-friend';
    const JOIN     = 'http://activitystrea.ms/schema/1.0/join';
    const TAG      = 'http://activitystrea.ms/schema/1.0/tag';

    // Custom OStatus verbs for the flipside until they're standardized
    const DELETE     = 'http://ostatus.org/schema/1.0/unfollow';
    const UNFAVORITE = 'http://ostatus.org/schema/1.0/unfavorite';
    const UNFOLLOW   = 'http://ostatus.org/schema/1.0/unfollow';
    const LEAVE      = 'http://ostatus.org/schema/1.0/leave';
}

class ActivityContext
{
    public $replyToID;
    public $replyToUrl;
    public $location;
    public $attention = array();
    public $conversation;

    const THR     = 'http://purl.org/syndication/thread/1.0';
    const GEORSS  = 'http://www.georss.org/georss';
    const OSTATUS = 'http://ostatus.org/schema/1.0';

    const INREPLYTO = 'in-reply-to';
    const REF       = 'ref';
    const HREF      = 'href';

    const POINT     = 'point';

    const ATTENTION    = 'ostatus:attention';
    const CONVERSATION = 'ostatus:conversation';

    function __construct($element)
    {
        $replyToEl = ActivityUtils::child($element, self::INREPLYTO, self::THR);

        if (!empty($replyToEl)) {
            $this->replyToID  = $replyToEl->getAttribute(self::REF);
            $this->replyToUrl = $replyToEl->getAttribute(self::HREF);
        }

        $this->location = $this->getLocation($element);

        $this->conversation = ActivityUtils::getLink($element, self::CONVERSATION);

        // Multiple attention links allowed

        $links = $element->getElementsByTagNameNS(ActivityUtils::ATOM, ActivityUtils::LINK);

        for ($i = 0; $i < $links->length; $i++) {

            $link = $links->item($i);

            $linkRel = $link->getAttribute(ActivityUtils::REL);

            if ($linkRel == self::ATTENTION) {
                $this->attention[] = $link->getAttribute(self::HREF);
            }
        }
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
        $points = $dom->getElementsByTagNameNS(self::GEORSS, self::POINT);

        for ($i = 0; $i < $points->length; $i++) {
            $point = $points->item($i)->textContent;
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

        return null;
    }
}

/**
 * An activity in the ActivityStrea.ms world
 *
 * An activity is kind of like a sentence: someone did something
 * to something else.
 *
 * 'someone' is the 'actor'; 'did something' is the verb;
 * 'something else' is the object.
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class Activity
{
    const SPEC   = 'http://activitystrea.ms/spec/1.0/';
    const SCHEMA = 'http://activitystrea.ms/schema/1.0/';

    const VERB       = 'verb';
    const OBJECT     = 'object';
    const ACTOR      = 'actor';
    const SUBJECT    = 'subject';
    const OBJECTTYPE = 'object-type';
    const CONTEXT    = 'context';
    const TARGET     = 'target';

    const ATOM = 'http://www.w3.org/2005/Atom';

    const AUTHOR    = 'author';
    const PUBLISHED = 'published';
    const UPDATED   = 'updated';

    public $actor;   // an ActivityObject
    public $verb;    // a string (the URL)
    public $object;  // an ActivityObject
    public $target;  // an ActivityObject
    public $context; // an ActivityObject
    public $time;    // Time of the activity
    public $link;    // an ActivityObject
    public $entry;   // the source entry
    public $feed;    // the source feed

    /**
     * Turns a regular old Atom <entry> into a magical activity
     *
     * @param DOMElement $entry Atom entry to poke at
     * @param DOMElement $feed  Atom feed, for context
     */

    function __construct($entry, $feed = null)
    {
        $this->entry = $entry;
        $this->feed  = $feed;

        $pubEl = $this->_child($entry, self::PUBLISHED, self::ATOM);

        if (!empty($pubEl)) {
            $this->time = strtotime($pubEl->textContent);
        } else {
            // XXX technically an error; being liberal. Good idea...?
            $updateEl = $this->_child($entry, self::UPDATED, self::ATOM);
            if (!empty($updateEl)) {
                $this->time = strtotime($updateEl->textContent);
            } else {
                $this->time = null;
            }
        }

        $this->link = ActivityUtils::getPermalink($entry);

        $verbEl = $this->_child($entry, self::VERB);

        if (!empty($verbEl)) {
            $this->verb = trim($verbEl->textContent);
        } else {
            $this->verb = ActivityVerb::POST;
            // XXX: do other implied stuff here
        }

        $objectEl = $this->_child($entry, self::OBJECT);

        if (!empty($objectEl)) {
            $this->object = new ActivityObject($objectEl);
        } else {
            $this->object = new ActivityObject($entry);
        }

        $actorEl = $this->_child($entry, self::ACTOR);

        if (!empty($actorEl)) {

            $this->actor = new ActivityObject($actorEl);

        } else if (!empty($feed) &&
                   $subjectEl = $this->_child($feed, self::SUBJECT)) {

            $this->actor = new ActivityObject($subjectEl);

        } else if ($authorEl = $this->_child($entry, self::AUTHOR, self::ATOM)) {

            $this->actor = new ActivityObject($authorEl);

        } else if (!empty($feed) && $authorEl = $this->_child($feed, self::AUTHOR,
                                                              self::ATOM)) {

            $this->actor = new ActivityObject($authorEl);
        }

        $contextEl = $this->_child($entry, self::CONTEXT);

        if (!empty($contextEl)) {
            $this->context = new ActivityContext($contextEl);
        } else {
            $this->context = new ActivityContext($entry);
        }

        $targetEl = $this->_child($entry, self::TARGET);

        if (!empty($targetEl)) {
            $this->target = new ActivityObject($targetEl);
        }
    }

    /**
     * Returns an Atom <entry> based on this activity
     *
     * @return DOMElement Atom entry
     */

    function toAtomEntry()
    {
        return null;
    }

    private function _child($element, $tag, $namespace=self::SPEC)
    {
        return ActivityUtils::child($element, $tag, $namespace);
    }
}