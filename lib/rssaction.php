<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for RSS 1.0 feed actions
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
 * @category  Mail
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Earle Martin <earle@downlode.org>
 * @copyright 2008-9 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) { exit(1); }

define('DEFAULT_RSS_LIMIT', 48);

class Rss10Action extends Action
{
    # This will contain the details of each feed item's author and be used to generate SIOC data.

    var $creators = array();
    var $limit = DEFAULT_RSS_LIMIT;
    var $notices = null;

    /**
     * Constructor
     *
     * Just wraps the Action constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see Action::__construct
     */

    function __construct($output='php://output', $indent=true)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */

    function isReadonly()
    {
        return true;
    }

    /**
     * Read arguments and initialize members
     *
     * @param array $args Arguments from $_REQUEST
     * @return boolean success
     */

    function prepare($args)
    {
        parent::prepare($args);
        $this->limit = (int) $this->trimmed('limit');
        if ($this->limit == 0) {
            $this->limit = DEFAULT_RSS_LIMIT;
        }
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */

    function handle($args)
    {
        // Parent handling, including cache check
        parent::handle($args);
        // Get the list of notices
        if (empty($this->tag)) {
            $this->notices = $this->getNotices($this->limit);
        } else {
            $this->notices = $this->getTaggedNotices($this->tag, $this->limit);
        }
        $this->showRss();
    }

    /**
     * Get the notices to output in this stream
     *
     * @return array an array of Notice objects sorted in reverse chron
     */

    function getNotices()
    {
        return array();
    }

    /**
     * Get a description of the channel
     *
     * Returns an array with the following
     * @return array
     */

    function getChannel()
    {
        return array('url' => '',
                     'title' => '',
                     'link' => '',
                     'description' => '');
    }

    function getImage()
    {
        return null;
    }

    function showRss()
    {
        $this->initRss();
        $this->showChannel();
        $this->showImage();

        foreach ($this->notices as $n) {
            $this->showItem($n);
        }

        $this->showCreators();
        $this->endRss();
    }

    function showChannel()
    {

        $channel = $this->getChannel();
        $image = $this->getImage();

        $this->elementStart('channel', array('rdf:about' => $channel['url']));
        $this->element('title', null, $channel['title']);
        $this->element('link', null, $channel['link']);
        $this->element('description', null, $channel['description']);
        $this->element('cc:licence', array('rdf:resource' => common_config('license','url')));

        if ($image) {
            $this->element('image', array('rdf:resource' => $image));
        }

        $this->elementStart('items');
        $this->elementStart('rdf:Seq');

        foreach ($this->notices as $notice) {
            $this->element('rdf:li', array('rdf:resource' => $notice->uri));
        }

        $this->elementEnd('rdf:Seq');
        $this->elementEnd('items');

        $this->elementEnd('channel');
    }

    function showImage()
    {
        $image = $this->getImage();
        if ($image) {
            $channel = $this->getChannel();
            $this->elementStart('image', array('rdf:about' => $image));
            $this->element('title', null, $channel['title']);
            $this->element('link', null, $channel['link']);
            $this->element('url', null, $image);
            $this->elementEnd('image');
        }
    }

    // XXX: Surely there should be a common function to do this?
    function extract_tags ($string)
    {
        $count = preg_match_all('/(?:^|\s)#([A-Za-z0-9_\-\.]{1,64})/', strtolower($string), $match);
        if (!count)
        {
            return array();
        }

        $rv = array();
        foreach ($match[1] as $tag)
        {
            $rv[] = common_canonical_tag($tag);
        } 

        return array_unique($rv);
    }
	 
    function showItem($notice)
    {
        $profile = Profile::staticGet($notice->profile_id);
        $nurl = common_local_url('shownotice', array('notice' => $notice->id));
        $creator_uri = common_profile_uri($profile);
        $this->elementStart('item', array('rdf:about' => $notice->uri,
                            'rdf:type' => 'http://rdfs.org/sioc/types#MicroblogPost'));
        $title = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
        $this->element('title', null, $title);
        $this->element('link', null, $nurl);
        $this->element('description', null, $profile->nickname."'s status on ".common_exact_date($notice->created));
        if ($notice->rendered) {
            $this->element('content:encoded', null, common_xml_safe_str($notice->rendered));
        }
        $this->element('dc:date', null, common_date_w3dtf($notice->created));
        $this->element('dc:creator', null, ($profile->fullname) ? $profile->fullname : $profile->nickname);
        $this->element('foaf:maker', array('rdf:resource' => $creator_uri));
        $this->element('sioc:has_creator', array('rdf:resource' => $creator_uri.'#acct'));
        $this->element('laconica:postIcon', array('rdf:resource' => $profile->avatarUrl()));
        $this->element('cc:licence', array('rdf:resource' => common_config('license', 'url')));
        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
            $this->element('sioc:reply_of', array('rdf:resource' => $replyurl));
        }
        $attachments = $notice->attachments();
        if($attachments){
            foreach($attachments as $attachment){
                if ($attachment->isEnclosure()) {
                    // DO NOT move xmlns declaration to root element. Making it
                    // the default namespace here improves compatibility with
                    // real-world feed readers.
                    $attribs = array(
                        'rdf:resource' => $attachment->url,
                        'url' => $attachment->url,
                        'xmlns' => 'http://purl.oclc.org/net/rss_2.0/enc#'
                        );
                    if ($attachment->title) {
                        $attribs['dc:title'] = $attachment->title;
                    }
                    if ($attachment->modified) {
                        $attribs['dc:date'] = common_date_w3dtf($attachment->modified);
                    }
                    if ($attachment->size) {
                        $attribs['length'] = $attachment->size;
                    }
                    if ($attachment->mimetype) {
                        $attribs['type'] = $attachment->mimetype;
                    }
                    $this->element('enclosure', $attribs);
                }
                $this->element('sioc:links_to', array('rdf:resource'=>$attachment->url));
            }
        }
        $tags = $this->extract_tags($notice->content);
        if (!empty($tags)) {
            foreach ($tags as $tag)
            {
                $tagpage = common_local_url('tag', array('tag' => $tag));
                $tagrss  = common_local_url('tagrss', array('tag' => $tag));
                $this->elementStart('ctag:tagged');
                $this->elementStart('ctag:Tag', array('rdf:about'=>$tagpage.'#concept', 'ctag:label'=>$tag));
                $this->element('foaf:page', array('rdf:resource'=>$tagpage));
                $this->element('rdfs:seeAlso', array('rdf:resource'=>$tagrss));
                $this->elementEnd('ctag:Tag');
                $this->elementEnd('ctag:tagged');
            }
        }
        $this->elementEnd('item');
        $this->creators[$creator_uri] = $profile;
    }

    function showCreators()
    {
        foreach ($this->creators as $uri => $profile) {
            $id = $profile->id;
            $nickname = $profile->nickname;
            $this->elementStart('foaf:Agent', array('rdf:about' => $uri));
            $this->element('foaf:nick', null, $nickname);
            if ($profile->fullname) {
                $this->element('foaf:name', null, $profile->fullname);
            }
            $this->element('foaf:holdsAccount', array('rdf:resource' => $uri.'#acct'));
            $avatar = $profile->avatarUrl();
            $this->element('foaf:depiction', array('rdf:resource' => $avatar));
            $this->elementEnd('foaf:Agent');
        }
    }

    function initRss()
    {
        $channel = $this->getChannel();
        header('Content-Type: application/rdf+xml');

        $this->startXml();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:dc' =>
                                              'http://purl.org/dc/elements/1.1/',
                                              'xmlns:cc' =>
                                              'http://creativecommons.org/ns#',
                                              'xmlns:content' =>
                                              'http://purl.org/rss/1.0/modules/content/',
                                              'xmlns:ctag' =>
                                              'http://commontag.org/ns#',
                                              'xmlns:foaf' =>
                                              'http://xmlns.com/foaf/0.1/',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns:sioct' =>
                                              'http://rdfs.org/sioc/types#',
                                              'xmlns:laconica' =>
                                              'http://laconi.ca/ont/',
                                              'xmlns' => 'http://purl.org/rss/1.0/'));
        $this->elementStart('sioc:Site', array('rdf:about' => common_root_url()));
        $this->element('sioc:name', null, common_config('site', 'name'));
        $this->elementStart('sioc:space_of');
        $this->element('sioc:Container', array('rdf:about' =>
                                               $channel['url']));
        $this->elementEnd('sioc:space_of');
        $this->elementEnd('sioc:Site');
    }

    function endRss()
    {
        $this->elementEnd('rdf:RDF');
    }

    /**
     * When was this page last modified?
     *
     */

    function lastModified()
    {
        if (empty($this->notices)) {
            return null;
        }

        if (count($this->notices) == 0) {
            return null;
        }

        // FIXME: doesn't handle modified profiles, avatars, deleted notices

        return strtotime($this->notices[0]->created);
    }
}

