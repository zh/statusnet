<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class FeedSubPreviewNotice extends Notice
{
    protected $fetched = true;

    function __construct($profile)
    {
        //parent::__construct(); // uhhh?
        $this->profile = $profile;
    }
    
    function getProfile()
    {
        return $this->profile;
    }
    
    function find()
    {
        return true;
    }
    
    function fetch()
    {
        $got = $this->fetched;
        $this->fetched = false;
        return $got;
    }
}

class FeedSubPreviewProfile extends Profile
{
    function getAvatar($width, $height=null)
    {
        return new FeedSubPreviewAvatar($width, $height);
    }
}

class FeedSubPreviewAvatar extends Avatar
{
    function displayUrl() {
        return common_path('plugins/FeedSub/images/48px-Feed-icon.svg.png');
    }
}

class FeedMunger
{
    /**
     * @param XML_Feed_Parser $feed
     */
    function __construct($feed, $url=null)
    {
        $this->feed = $feed;
        $this->url = $url;
    }
    
    function feedinfo()
    {
        $feedinfo = new Feedinfo();
        $feedinfo->feeduri = $this->url;
        $feedinfo->homeuri = $this->feed->link;
        $feedinfo->huburi = $this->getHubLink();
        return $feedinfo;
    }

    function getAtomLink($item, $attribs=array())
    {
        // XML_Feed_Parser gets confused by multiple <link> elements.
        $dom = $item->model;

        // Note that RSS feeds would embed an <atom:link> so this should work for both.
        /// http://code.google.com/p/pubsubhubbub/wiki/RssFeeds
        // <link rel='hub' href='http://pubsubhubbub.appspot.com/'/>
        $links = $dom->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link');
        for ($i = 0; $i < $links->length; $i++) {
            $node = $links->item($i);
            if ($node->hasAttributes()) {
                $href = $node->attributes->getNamedItem('href');
                if ($href) {
                    $matches = 0;
                    foreach ($attribs as $name => $val) {
                        $attrib = $node->attributes->getNamedItem($name);
                        if ($attrib && $attrib->value == $val) {
                            $matches++;
                        }
                    }
                    if ($matches == count($attribs)) {
                        return $href->value;
                    }
                }
            }
        }
        return false;
    }

    function getRssLink($item)
    {
        // XML_Feed_Parser gets confused by multiple <link> elements.
        $dom = $item->model;

        // Note that RSS feeds would embed an <atom:link> so this should work for both.
        /// http://code.google.com/p/pubsubhubbub/wiki/RssFeeds
        // <link rel='hub' href='http://pubsubhubbub.appspot.com/'/>
        $links = $dom->getElementsByTagName('link');
        for ($i = 0; $i < $links->length; $i++) {
            $node = $links->item($i);
            if (!$node->hasAttributes()) {
                return $node->textContent;
            }
        }
        return false;
    }

    function getAltLink($item)
    {
        // Check for an atom link...
        $link = $this->getAtomLink($item, array('rel' => 'alternate', 'type' => 'text/html'));
        if (!$link) {
            $link = $this->getRssLink($item);
        }
        return $link;
    }

    function getHubLink()
    {
        return $this->getAtomLink($this->feed, array('rel' => 'hub'));
    }

    function profile($preview=false)
    {
        if ($preview) {
            $profile = new FeedSubPreviewProfile();
        } else {
            $profile = new Profile();
        }
        
        // @todo validate/normalize nick?
        $profile->nickname   = $this->feed->title;
        $profile->fullname   = $this->feed->title;
        $profile->homepage   = $this->getAltLink($this->feed);
        $profile->bio        = $this->feed->description;
        $profile->profileurl = $this->getAltLink($this->feed);
        
        // @todo tags from categories
        // @todo lat/lon/location?

        return $profile;
    }

    function notice($index=1, $preview=false)
    {
        $entry = $this->feed->getEntryByOffset($index);
        if (!$entry) {
            return null;
        }
        
        if ($preview) {
            $notice = new FeedSubPreviewNotice($this->profile(true));
            $notice->id = -1;
        } else {
            $notice = new Notice();
        }

        $link = $this->getAltLink($entry);
        $notice->uri = $link;
        $notice->url = $link;
        $notice->content = $this->noticeFromEntry($entry);
        $notice->rendered = common_render_content($notice->content, $notice);
        $notice->created = common_sql_date($entry->updated); // @fixme
        $notice->is_local = Notice::GATEWAY;
        $notice->source = 'feed';
        
        return $notice;
    }

    /**
     * @param XML_Feed_Type $entry
     * @return string notice text, within post size limit
     */
    function noticeFromEntry($entry)
    {
        $title = $entry->title;
        $link = $entry->link;
        
        // @todo We can get <category> entries like this:
        // $cats = $entry->getCategory('category', array(0, true));
        // but it feels like an awful hack. If it's accessible cleanly,
        // try adding #hashtags from the categories/tags on a post.
        
        // @todo Should we force a language here?
        $format = dgettext("FeedSubPlugin", 'New post: "%1$s" %2$s');
        $title = $entry->title;
        $link = $this->getAltLink($entry);
        $out = sprintf($format, $title, $link);
        
        // Trim link if needed...
        $max = Notice::maxContent();
        if (mb_strlen($out) > $max) {
            $link = common_shorten_url($link);
            $out = sprintf($format, $title, $link);
        }

        // Trim title if needed...
        if (mb_strlen($out) > $max) {
            $ellipsis = "\xe2\x80\xa6"; // U+2026 HORIZONTAL ELLIPSIS
            $used = mb_strlen($out) - mb_strlen($title);
            $available = $max - $used - mb_strlen($ellipsis);
            $title = mb_substr($title, 0, $available) . $ellipsis;
            $out = sprintf($format, $title, $link);
        }
        
        return $out;
    }
}
