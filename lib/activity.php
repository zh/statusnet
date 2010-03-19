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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PoCoURL
{
    const URLS      = 'urls';
    const TYPE      = 'type';
    const VALUE     = 'value';
    const PRIMARY   = 'primary';

    public $type;
    public $value;
    public $primary;

    function __construct($type, $value, $primary = false)
    {
        $this->type    = $type;
        $this->value   = $value;
        $this->primary = $primary;
    }

    function asString()
    {
        $xs = new XMLStringer(true);
        $xs->elementStart('poco:urls');
        $xs->element('poco:type', null, $this->type);
        $xs->element('poco:value', null, $this->value);
        if (!empty($this->primary)) {
            $xs->element('poco:primary', null, 'true');
        }
        $xs->elementEnd('poco:urls');
        return $xs->getString();
    }
}

class PoCoAddress
{
    const ADDRESS   = 'address';
    const FORMATTED = 'formatted';

    public $formatted;

    // @todo Other address fields

    function asString()
    {
        if (!empty($this->formatted)) {
            $xs = new XMLStringer(true);
            $xs->elementStart('poco:address');
            $xs->element('poco:formatted', null, common_xml_safe_str($this->formatted));
            $xs->elementEnd('poco:address');
            return $xs->getString();
        }

        return null;
    }
}

class PoCo
{
    const NS = 'http://portablecontacts.net/spec/1.0';

    const USERNAME     = 'preferredUsername';
    const DISPLAYNAME  = 'displayName';
    const NOTE         = 'note';

    public $preferredUsername;
    public $displayName;
    public $note;
    public $address;
    public $urls = array();

    function __construct($element = null)
    {
        if (empty($element)) {
            return;
        }

        $this->preferredUsername = ActivityUtils::childContent(
            $element,
            self::USERNAME,
            self::NS
        );

        $this->displayName = ActivityUtils::childContent(
            $element,
            self::DISPLAYNAME,
            self::NS
        );

        $this->note = ActivityUtils::childContent(
            $element,
            self::NOTE,
            self::NS
        );

        $this->address = $this->_getAddress($element);
        $this->urls = $this->_getURLs($element);
    }

    private function _getURLs($element)
    {
        $urlEls = $element->getElementsByTagnameNS(self::NS, PoCoURL::URLS);
        $urls = array();

        foreach ($urlEls as $urlEl) {

            $type = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::TYPE,
                PoCo::NS
            );

            $value = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::VALUE,
                PoCo::NS
            );

            $primary = ActivityUtils::childContent(
                $urlEl,
                PoCoURL::PRIMARY,
                PoCo::NS
            );

            $isPrimary = false;

            if (isset($primary) && $primary == 'true') {
                $isPrimary = true;
            }

            // @todo check to make sure a primary hasn't already been added

            array_push($urls, new PoCoURL($type, $value, $isPrimary));
        }
        return $urls;
    }

    private function _getAddress($element)
    {
        $addressEl = ActivityUtils::child(
            $element,
            PoCoAddress::ADDRESS,
            PoCo::NS
        );

        if (!empty($addressEl)) {
            $formatted = ActivityUtils::childContent(
                $addressEl,
                PoCoAddress::FORMATTED,
                self::NS
            );

            if (!empty($formatted)) {
                $address = new PoCoAddress();
                $address->formatted = $formatted;
                return $address;
            }
        }

        return null;
    }

    function fromProfile($profile)
    {
        if (empty($profile)) {
            return null;
        }

        $poco = new PoCo();

        $poco->preferredUsername = $profile->nickname;
        $poco->displayName       = $profile->getBestName();

        $poco->note = $profile->bio;

        $paddy = new PoCoAddress();
        $paddy->formatted = $profile->location;
        $poco->address = $paddy;

        if (!empty($profile->homepage)) {
            array_push(
                $poco->urls,
                new PoCoURL(
                    'homepage',
                    $profile->homepage,
                    true
                )
            );
        }

        return $poco;
    }

    function fromGroup($group)
    {
        if (empty($group)) {
            return null;
        }

        $poco = new PoCo();

        $poco->preferredUsername = $group->nickname;
        $poco->displayName       = $group->getBestName();

        $poco->note = $group->description;

        $paddy = new PoCoAddress();
        $paddy->formatted = $group->location;
        $poco->address = $paddy;

        if (!empty($group->homepage)) {
            array_push(
                $poco->urls,
                new PoCoURL(
                    'homepage',
                    $group->homepage,
                    true
                )
            );
        }

        return $poco;
    }

    function getPrimaryURL()
    {
        foreach ($this->urls as $url) {
            if ($url->primary) {
                return $url;
            }
        }
    }

    function asString()
    {
        $xs = new XMLStringer(true);
        $xs->element(
            'poco:preferredUsername',
            null,
            $this->preferredUsername
        );

        $xs->element(
            'poco:displayName',
            null,
            $this->displayName
        );

        if (!empty($this->note)) {
            $xs->element('poco:note', null, common_xml_safe_str($this->note));
        }

        if (!empty($this->address)) {
            $xs->raw($this->address->asString());
        }

        foreach ($this->urls as $url) {
            $xs->raw($url->asString());
        }

        return $xs->getString();
    }
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

    const CONTENT = 'content';
    const SRC     = 'src';

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

    static function getLink(DOMNode $element, $rel, $type=null)
    {
        $els = $element->childNodes;

        foreach ($els as $link) {
            if ($link->localName == self::LINK && $link->namespaceURI == self::ATOM) {

                $linkRel = $link->getAttribute(self::REL);
                $linkType = $link->getAttribute(self::TYPE);

                if ($linkRel == $rel &&
                    (is_null($type) || $linkType == $type)) {
                    return $link->getAttribute(self::HREF);
                }
            }
        }

        return null;
    }

    static function getLinks(DOMNode $element, $rel, $type=null)
    {
        $els = $element->childNodes;
        $out = array();

        foreach ($els as $link) {
            if ($link->localName == self::LINK && $link->namespaceURI == self::ATOM) {

                $linkRel = $link->getAttribute(self::REL);
                $linkType = $link->getAttribute(self::TYPE);

                if ($linkRel == $rel &&
                    (is_null($type) || $linkType == $type)) {
                    $out[] = $link;
                }
            }
        }

        return $out;
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

    static function child(DOMNode $element, $tag, $namespace=self::ATOM)
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

    static function childContent(DOMNode $element, $tag, $namespace=self::ATOM)
    {
        $el = self::child($element, $tag, $namespace);

        if (empty($el)) {
            return null;
        } else {
            return $el->textContent;
        }
    }

    /**
     * Get the content of an atom:entry-like object
     *
     * @param DOMElement $element The element to examine.
     *
     * @return string unencoded HTML content of the element, like "This -&lt; is <b>HTML</b>."
     *
     * @todo handle remote content
     * @todo handle embedded XML mime types
     * @todo handle base64-encoded non-XML and non-text mime types
     */

    static function getContent($element)
    {
        $contentEl = ActivityUtils::child($element, self::CONTENT);

        if (!empty($contentEl)) {

            $src  = $contentEl->getAttribute(self::SRC);

            if (!empty($src)) {
                throw new ClientException(_("Can't handle remote content yet."));
            }

            $type = $contentEl->getAttribute(self::TYPE);

            // slavishly following http://atompub.org/rfc4287.html#rfc.section.4.1.3.3

            if (empty($type) || $type == 'text') {
                return $contentEl->textContent;
            } else if ($type == 'html') {
                $text = $contentEl->textContent;
                return htmlspecialchars_decode($text, ENT_QUOTES);
            } else if ($type == 'xhtml') {
                $divEl = ActivityUtils::child($contentEl, 'div', 'http://www.w3.org/1999/xhtml');
                if (empty($divEl)) {
                    return null;
                }
                $doc = $divEl->ownerDocument;
                $text = '';
                $children = $divEl->childNodes;

                for ($i = 0; $i < $children->length; $i++) {
                    $child = $children->item($i);
                    $text .= $doc->saveXML($child);
                }
                return trim($text);
            } else if (in_array($type, array('text/xml', 'application/xml')) ||
                       preg_match('#(+|/)xml$#', $type)) {
                throw new ClientException(_("Can't handle embedded XML content yet."));
            } else if (strncasecmp($type, 'text/', 5)) {
                return $contentEl->textContent;
            } else {
                throw new ClientException(_("Can't handle embedded Base64 content yet."));
            }
        }
    }
}

// XXX: Arg! This wouldn't be necessary if we used Avatars conistently
class AvatarLink
{
    public $url;
    public $type;
    public $size;
    public $width;
    public $height;

    function __construct($element=null)
    {
        if ($element) {
            // @fixme use correct namespaces
            $this->url = $element->getAttribute('href');
            $this->type = $element->getAttribute('type');
            $width = $element->getAttribute('media:width');
            if ($width != null) {
                $this->width = intval($width);
            }
            $height = $element->getAttribute('media:height');
            if ($height != null) {
                $this->height = intval($height);
            }
        }
    }

    static function fromAvatar($avatar)
    {
        if (empty($avatar)) {
            return null;
        }
        $alink = new AvatarLink();
        $alink->type   = $avatar->mediatype;
        $alink->height = $avatar->height;
        $alink->width  = $avatar->width;
        $alink->url    = $avatar->displayUrl();
        return $alink;
    }

    static function fromFilename($filename, $size)
    {
        $alink = new AvatarLink();
        $alink->url    = $filename;
        $alink->height = $size;
        if (!empty($filename)) {
            $alink->width  = $size;
            $alink->type   = self::mediatype($filename);
        } else {
            $alink->url    = User_group::defaultLogo($size);
            $alink->type   = 'image/png';
        }
        return $alink;
    }

    // yuck!
    static function mediatype($filename) {
        $ext = strtolower(end(explode('.', $filename)));
        if ($ext == 'jpeg') {
            $ext = 'jpg';
        }
        // hope we don't support any others
        $types = array('png', 'gif', 'jpg', 'jpeg');
        if (in_array($ext, $types)) {
            return 'image/' . $ext;
        }
        return null;
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
    public $avatarLinks = array();
    public $geopoint;
    public $poco;
    public $displayName;

    /**
     * Constructor
     *
     * This probably needs to be refactored
     * to generate a local class (ActivityPerson, ActivityFile, ...)
     * based on the object type.
     *
     * @param DOMElement $element DOM thing to turn into an Activity thing
     */

    function __construct($element = null)
    {
        if (empty($element)) {
            return;
        }

        $this->element = $element;

        $this->geopoint = $this->_childContent(
            $element,
            ActivityContext::POINT,
            ActivityContext::GEORSS
        );

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

            $this->source  = $this->_getSource($element);

            $this->content = ActivityUtils::getContent($element);

            $this->link = ActivityUtils::getPermalink($element);

        }

        // Some per-type attributes...
        if ($this->type == self::PERSON || $this->type == self::GROUP) {
            $this->displayName = $this->title;

            $photos = ActivityUtils::getLinks($element, 'photo');
            if (count($photos)) {
                foreach ($photos as $link) {
                    $this->avatarLinks[] = new AvatarLink($link);
                }
            } else {
                $avatars = ActivityUtils::getLinks($element, 'avatar');
                foreach ($avatars as $link) {
                    $this->avatarLinks[] = new AvatarLink($link);
                }
            }

            $this->poco = new PoCo($element);
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

    static function fromNotice(Notice $notice)
    {
        $object = new ActivityObject();

        $object->type    = ActivityObject::NOTE;

        $object->id      = $notice->uri;
        $object->title   = $notice->content;
        $object->content = $notice->rendered;
        $object->link    = $notice->bestUrl();

        return $object;
    }

    static function fromProfile(Profile $profile)
    {
        $object = new ActivityObject();

        $object->type   = ActivityObject::PERSON;
        $object->id     = $profile->getUri();
        $object->title  = $profile->getBestName();
        $object->link   = $profile->profileurl;

        $orig = $profile->getOriginalAvatar();

        if (!empty($orig)) {
            $object->avatarLinks[] = AvatarLink::fromAvatar($orig);
        }

        $sizes = array(
            AVATAR_PROFILE_SIZE,
            AVATAR_STREAM_SIZE,
            AVATAR_MINI_SIZE
        );

        foreach ($sizes as $size) {

            $alink  = null;
            $avatar = $profile->getAvatar($size);

            if (!empty($avatar)) {
                $alink = AvatarLink::fromAvatar($avatar);
            } else {
                $alink = new AvatarLink();
                $alink->type   = 'image/png';
                $alink->height = $size;
                $alink->width  = $size;
                $alink->url    = Avatar::defaultImage($size);
            }

            $object->avatarLinks[] = $alink;
        }

        if (isset($profile->lat) && isset($profile->lon)) {
            $object->geopoint = (float)$profile->lat
                . ' ' . (float)$profile->lon;
        }

        $object->poco = PoCo::fromProfile($profile);

        return $object;
    }

    static function fromGroup($group)
    {
        $object = new ActivityObject();

        $object->type   = ActivityObject::GROUP;
        $object->id     = $group->getUri();
        $object->title  = $group->getBestName();
        $object->link   = $group->getUri();

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->homepage_logo,
            AVATAR_PROFILE_SIZE
        );

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->stream_logo,
            AVATAR_STREAM_SIZE
        );

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->mini_logo,
            AVATAR_MINI_SIZE
        );

        $object->poco = PoCo::fromGroup($group);

        return $object;
    }

    function asString($tag='activity:object')
    {
        $xs = new XMLStringer(true);

        $xs->elementStart($tag);

        $xs->element('activity:object-type', null, $this->type);

        $xs->element(self::ID, null, $this->id);

        if (!empty($this->title)) {
            $xs->element(
                self::TITLE,
                null,
                common_xml_safe_str($this->title)
            );
        }

        if (!empty($this->summary)) {
            $xs->element(
                self::SUMMARY,
                null,
                common_xml_safe_str($this->summary)
            );
        }

        if (!empty($this->content)) {
            // XXX: assuming HTML content here
            $xs->element(
                ActivityUtils::CONTENT,
                array('type' => 'html'),
                common_xml_safe_str($this->content)
            );
        }

        if (!empty($this->link)) {
            $xs->element(
                'link',
                array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => $this->link
                ),
                null
            );
        }

        if ($this->type == ActivityObject::PERSON
            || $this->type == ActivityObject::GROUP) {

            foreach ($this->avatarLinks as $avatar) {
                $xs->element(
                    'link', array(
                        'rel'  => 'avatar',
                        'type'         => $avatar->type,
                        'media:width'  => $avatar->width,
                        'media:height' => $avatar->height,
                        'href' => $avatar->url
                    ),
                    null
                );
            }
        }

        if (!empty($this->geopoint)) {
            $xs->element(
                'georss:point',
                null,
                $this->geopoint
            );
        }

        if (!empty($this->poco)) {
            $xs->raw($this->poco->asString());
        }

        $xs->elementEnd($tag);

        return $xs->getString();
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

    // For simple profile-update pings; no content to share.
    const UPDATE_PROFILE = 'http://ostatus.org/schema/1.0/update-profile';
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
            return self::locationFromPoint($point);
        }

        return null;
    }

    // XXX: Move to ActivityUtils or Location?
    static function locationFromPoint($point)
    {
        $point = str_replace(',', ' ', $point); // per spec "treat commas as whitespace"
        $point = preg_replace('/\s+/', ' ', $point);
        $point = trim($point);
        $coords = explode(' ', $point);
        if (count($coords) == 2) {
            list($lat, $lon) = $coords;
            if (is_numeric($lat) && is_numeric($lon)) {
                common_log(LOG_INFO, "Looking up location for $lat $lon from georss point");
                return Location::fromLatLon($lat, $lon);
            }
        }
        common_log(LOG_ERR, "Ignoring bogus georss:point value $point");
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

    public $summary; // summary of activity
    public $content; // HTML content of activity
    public $id;      // ID of the activity
    public $title;   // title of the activity
    public $categories = array(); // list of AtomCategory objects
    public $enclosures = array(); // list of enclosure URL references

    /**
     * Turns a regular old Atom <entry> into a magical activity
     *
     * @param DOMElement $entry Atom entry to poke at
     * @param DOMElement $feed  Atom feed, for context
     */

    function __construct($entry = null, $feed = null)
    {
        if (is_null($entry)) {
            return;
        }

        $this->entry = $entry;

        // Insist on a feed's root DOMElement; don't allow a DOMDocument
        if ($feed instanceof DOMDocument) {
            throw new ClientException(
                _("Expecting a root feed element but got a whole XML document.")
            );
        }

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

        $this->summary = ActivityUtils::childContent($entry, 'summary');
        $this->id      = ActivityUtils::childContent($entry, 'id');
        $this->content = ActivityUtils::getContent($entry);

        $catEls = $entry->getElementsByTagNameNS(self::ATOM, 'category');
        if ($catEls) {
            for ($i = 0; $i < $catEls->length; $i++) {
                $catEl = $catEls->item($i);
                $this->categories[] = new AtomCategory($catEl);
            }
        }

        foreach (ActivityUtils::getLinks($entry, 'enclosure') as $link) {
            $this->enclosures[] = $link->getAttribute('href');
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

    function asString($namespace=false)
    {
        $xs = new XMLStringer(true);

        if ($namespace) {
            $attrs = array('xmlns' => 'http://www.w3.org/2005/Atom',
                           'xmlns:activity' => 'http://activitystrea.ms/spec/1.0/',
                           'xmlns:georss' => 'http://www.georss.org/georss',
                           'xmlns:ostatus' => 'http://ostatus.org/schema/1.0',
                           'xmlns:poco' => 'http://portablecontacts.net/spec/1.0',
                           'xmlns:media' => 'http://purl.org/syndication/atommedia');
        } else {
            $attrs = array();
        }

        $xs->elementStart('entry', $attrs);

        $xs->element('id', null, $this->id);
        $xs->element('title', null, $this->title);
        $xs->element('published', null, common_date_iso8601($this->time));
        $xs->element('content', array('type' => 'html'), $this->content);

        if (!empty($this->summary)) {
            $xs->element('summary', null, $this->summary);
        }

        if (!empty($this->link)) {
            $xs->element('link', array('rel' => 'alternate',
                                       'type' => 'text/html'),
                         $this->link);
        }

        // XXX: add context

        $xs->elementStart('author');
        $xs->element('uri', array(), $this->actor->id);
        if ($this->actor->title) {
            $xs->element('name', array(), $this->actor->title);
        }
        $xs->elementEnd('author');
        $xs->raw($this->actor->asString('activity:actor'));

        $xs->element('activity:verb', null, $this->verb);

        if ($this->object) {
            $xs->raw($this->object->asString());
        }

        if ($this->target) {
            $xs->raw($this->target->asString('activity:target'));
        }

        foreach ($this->categories as $cat) {
            $xs->raw($cat->asString());
        }

        $xs->elementEnd('entry');

        return $xs->getString();
    }

    private function _child($element, $tag, $namespace=self::SPEC)
    {
        return ActivityUtils::child($element, $tag, $namespace);
    }
}

class AtomCategory
{
    public $term;
    public $scheme;
    public $label;

    function __construct($element=null)
    {
        if ($element && $element->attributes) {
            $this->term = $this->extract($element, 'term');
            $this->scheme = $this->extract($element, 'scheme');
            $this->label = $this->extract($element, 'label');
        }
    }

    protected function extract($element, $attrib)
    {
        $node = $element->attributes->getNamedItemNS(Activity::ATOM, $attrib);
        if ($node) {
            return trim($node->textContent);
        }
        $node = $element->attributes->getNamedItem($attrib);
        if ($node) {
            return trim($node->textContent);
        }
        return null;
    }

    function asString()
    {
        $attribs = array();
        if ($this->term !== null) {
            $attribs['term'] = $this->term;
        }
        if ($this->scheme !== null) {
            $attribs['scheme'] = $this->scheme;
        }
        if ($this->label !== null) {
            $attribs['label'] = $this->label;
        }
        $xs = new XMLStringer();
        $xs->element('category', $attribs);
        return $xs->asString();
    }
}
